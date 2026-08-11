#!/usr/bin/env python3
"""
Build the ChamberQ medicine catalogue (full Bangladesh market, tiered).

Supersedes the ~460-row curated build (see decisions.md, 2026-08-10 and the
owner's override). Every brand marketed in Bangladesh is emitted; safety and
usability come from the *priority tier*, not from leaving rows out.

    tier 0  pinned      hand-verified household brands (strength/form checked)
    tier 1  curated     the reviewed 460 seed, kept verbatim
    tier 2  essential   generic on the Bangladesh NEML or the WHO EML
    tier 3  standard    other outpatient forms
    tier 4  specialist  parenteral, chemo, vaccines — real, but never the
                        first thing a chamber doctor sees in a picker

Source (CC BY 4.0, not committed — download once):
    BDDrugBank v1.0.0, DOI 10.5281/zenodo.20749707
    https://zenodo.org/records/20749707
    unzip into /tmp/bddrugbank/

    python3 data/build-medicine-catalogue.py
"""

from __future__ import annotations

import argparse
import csv
import re
import sys
from collections import Counter
from dataclasses import dataclass, field
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = Path("/tmp/bddrugbank")
DEFAULT_SOURCE = SOURCE_DIR / "medex_merged.csv"
DEFAULT_NEML = SOURCE_DIR / "bangladesh_neml_2016.csv"
DEFAULT_WHO_EML = SOURCE_DIR / "who_eml_2025.csv"
CURATED_SEED = ROOT / "data" / "medicine-curated-seed.csv"
OUTPUT = ROOT / "data" / "medicine-list-draft.csv"

MIN_ROWS = 24000

# One catalogue row per brand + strength + form, not per brand. NAPA alone
# ships as a 500 mg tablet, a 120 mg/5 ml syrup, 80 mg/ml paediatric drops,
# three suppository strengths and an IV infusion — collapsing those to one
# row discards 8,656 SKUs across 5,862 brands, and the losses fall hardest on
# exactly the syrups and drops a chamber GP needs for children.
# `medicines.brand_name` is indexed, not unique, so this needs no constraint
# change; the loader upserts on the same triple.
def sku_key(brand: str, strength: str, form: str) -> tuple[str, str, str]:
    return (brand, (strength or "").strip().lower(), (form or "").strip().lower())

TIER_PINNED, TIER_CURATED, TIER_ESSENTIAL, TIER_STANDARD, TIER_SPECIALIST = 0, 1, 2, 3, 4

# Forms a doctor cannot hand a patient in a chamber. Kept in the catalogue —
# a hospital-linked doctor may genuinely need them — but demoted so they never
# outrank an oral brand of the same name. This is the `ACE IV` vs
# `ACE 500 mg tablet` ambiguity the curated build avoided by exclusion.
PARENTERAL = re.compile(r"\b(iv|im|infusion|injection|parenteral|intrathecal|intravenous)\b", re.I)

SPECIALIST_CLASS = re.compile(r"(cytotoxic|chemotherapy|vaccine|immunoglobulin|radiopharma)", re.I)

# Coarse category for the long tail, matched against therapeutic_class in
# order. The curated seed keeps its own reviewed category untouched.
CATEGORY_RULES: list[tuple[str, str]] = [
    (r"penicillin|cephalosporin|macrolide|quinolone|tetracycline|aminoglycoside|antibacterial|antibiotic|carbapenem|sulfonamide", "Antibiotic"),
    (r"antifungal|mycoses", "Antifungal"),
    (r"anthelmintic|antiprotozoal|antimalarial|amoebic", "Anthelmintic"),
    (r"antiviral|antiretroviral", "Antiviral"),
    (r"proton pump|antacid|anti-emetic|laxative|anti-diarrhoeal|ulcer|hepat|gastro", "GI"),
    (r"antihistamine|leukotriene|allergy", "Allergy"),
    (r"nsaid|analgesic|opioid|arthritis|gout|migraine", "Analgesic"),
    (r"antihypertensive|angiotensin|beta.?blocker|calcium channel|statin|anti-anginal|diuretic|cardiac|anticoagulant|antiplatelet|heart", "Cardiac"),
    (r"hypoglycemic|insulin|diabet", "Diabetes"),
    (r"corticosteroid|glucocorticoid|steroid", "Steroid"),
    (r"asthma|bronchodilator|respiratory|cough|expectorant|mucolytic", "Respiratory"),
    (r"dermat|acne|psoriasis|eczema|topical|scabies", "Dermatology"),
    (r"ophthalmic|eye|otic|ear|nasal|ent", "Eye/ENT"),
    (r"anti-epileptic|antidepressant|antipsychotic|anxiolytic|hypnotic|sedative|psych|parkinson|dementia", "Psychiatry"),
    (r"thyroid", "Thyroid"),
    (r"urolog|prostat|erectile|urinary", "Urology"),
    (r"contracept|oestrogen|estrogen|progest|obstetric|gynae|gynec|uterine", "Gynecology"),
    (r"vitamin|mineral|supplement|nutrition|electrolyte", "Supplement"),
    (r"rehydration|oral rehydration", "Rehydration"),
    (r"muscle relaxant|musculoskeletal|spasm", "Musculoskeletal"),
    (r"dental|oral hygiene|mouthwash", "Dental"),
    (r"vaccine|immunoglobulin", "Vaccine"),
    (r"cytotoxic|chemotherapy|oncolog|antineoplastic", "Oncology"),
]

PRACTICE_BY_CATEGORY = {
    "Dental": "dentist|general_physician",
    "Gynecology": "gynecologist|general_physician",
    "Pediatric": "pediatrician|general_physician",
    "Dermatology": "dermatologist|general_physician",
}

FORM_MAP = {
    "powder for suspension": "syrup",
    "oral suspension": "syrup",
    "suspension": "syrup",
    "oral solution": "solution",
    "ophthalmic": "drops",
    "eye drop": "drops",
    "ear drop": "drops",
    "nasal": "drops",
    "drops": "drops",
    "chewable": "tablet",
    "enteric coated": "tablet",
    "extended release": "tablet",
    "tablet": "tablet",
    "capsule": "capsule",
    "syrup": "syrup",
    "cream": "cream",
    "ointment": "ointment",
    "gel": "gel",
    "lotion": "lotion",
    "inhaler": "inhaler",
    "metered-dose": "inhaler",
    "dry powder": "inhaler",
    "nebuliser": "inhaler",
    "sachet": "sachet",
    "powder": "sachet",
    "mouthwash": "mouthwash",
    "suppository": "suppository",
    "patch": "patch",
    "infusion": "injection",
    "injection": "injection",
    "solution": "solution",
}


@dataclass
class MedRow:
    brand_name: str
    generic_name: str
    default_strength: str
    form: str
    aliases: str
    category: str
    practice_types: str
    indications: str = ""
    manufacturer: str = ""
    is_essential: int = 0
    priority: int = TIER_STANDARD
    _seen_forms: set[str] = field(default_factory=set, repr=False)


def clean_brand(name: str) -> str:
    return re.sub(r"\s+", " ", (name or "").strip()).upper()


def norm_generic(value: str) -> str:
    return re.sub(r"[^a-z0-9+ ]", "", (value or "").strip().lower())


def normalize_form(raw: str) -> str:
    key = (raw or "").strip().lower()
    for needle, mapped in FORM_MAP.items():
        if needle in key:
            return mapped
    return "tablet"


def category_for(therapeutic_class: str, generic: str) -> str:
    haystack = f"{therapeutic_class} {generic}".lower()
    for pattern, category in CATEGORY_RULES:
        if re.search(pattern, haystack):
            return category
    return "Other"


def practice_types_for(category: str) -> str:
    return PRACTICE_BY_CATEGORY.get(category, "")


def aliases_for(brand: str, generic: str) -> str:
    parts = {brand.lower()}
    if generic:
        parts.add(generic.lower())
        # Search on the first word of a combination generic too, so
        # "amoxicillin" finds "Amoxicillin + Clavulanic Acid".
        head = re.split(r"[+/,]", generic)[0].strip().lower()
        if head:
            parts.add(head)
    return "|".join(sorted(p for p in parts if p))


def short_indication(therapeutic_class: str, indications: str) -> str:
    """
    Picker subtitle. `therapeutic_class` is already concise and clinical
    (median 34 chars, e.g. "Proton Pump Inhibitor"); the free-text
    `indications` blob averages 638 characters of brand-prefixed marketing
    prose, so it is only a fallback.
    """
    tc = re.sub(r"\s+", " ", (therapeutic_class or "").strip())
    if tc:
        return tc[:120]

    text = re.sub(r"\s+", " ", (indications or "").strip())
    if not text:
        return ""
    text = re.split(r"(?<=[.;])\s", text)[0]
    return text[:120]


def load_essential_generics(neml_path: Path, who_path: Path) -> set[str]:
    essential: set[str] = set()

    if neml_path.is_file():
        with neml_path.open(newline="", encoding="utf-8") as handle:
            for row in csv.DictReader(handle):
                value = norm_generic(row.get("neml_generic_norm") or "")
                if value:
                    essential.add(value)

    if who_path.is_file():
        with who_path.open(newline="", encoding="utf-8") as handle:
            for row in csv.DictReader(handle):
                value = norm_generic(row.get("inn_norm") or "")
                if value:
                    essential.add(value)

    return essential


def load_curated_seed(path: Path) -> dict[tuple[str, str, str], MedRow]:
    """The reviewed 460 — kept verbatim, never re-derived from the source."""
    seed: dict[tuple[str, str, str], MedRow] = {}

    if not path.is_file():
        print(f"Curated seed not found: {path}", file=sys.stderr)
        return seed

    with path.open(newline="", encoding="utf-8") as handle:
        for row in csv.DictReader(handle):
            brand = clean_brand(row.get("brand_name") or "")
            if not brand:
                continue
            key = sku_key(brand, row.get("default_strength") or "", row.get("form") or "tablet")
            seed[key] = MedRow(
                brand_name=brand,
                generic_name=(row.get("generic_name") or "").strip(),
                default_strength=(row.get("default_strength") or "").strip(),
                form=(row.get("form") or "tablet").strip(),
                aliases=(row.get("aliases") or "").strip(),
                category=(row.get("category") or "Other").strip(),
                practice_types=(row.get("practice_types") or "").strip(),
                priority=TIER_CURATED,
            )

    return seed


def tier_for(form: str, therapeutic_class: str, is_essential: bool, raw_form: str) -> int:
    if PARENTERAL.search(raw_form) or SPECIALIST_CLASS.search(therapeutic_class or ""):
        return TIER_SPECIALIST
    if is_essential:
        return TIER_ESSENTIAL
    return TIER_STANDARD


def build_catalogue(source_path: Path, neml_path: Path, who_path: Path) -> list[MedRow]:
    if not source_path.is_file():
        print(f"Source not found: {source_path}", file=sys.stderr)
        print("Download BDDrugBank v1.0.0 (CC BY 4.0) from", file=sys.stderr)
        print("  https://zenodo.org/records/20749707", file=sys.stderr)
        print(f"and unzip into {SOURCE_DIR}", file=sys.stderr)
        sys.exit(1)

    essential = load_essential_generics(neml_path, who_path)
    catalogue = load_curated_seed(CURATED_SEED)

    # Curated rows keep their reviewed values but still gain the new columns.
    for med in catalogue.values():
        med.is_essential = int(norm_generic(med.generic_name) in essential)

    with source_path.open(newline="", encoding="utf-8") as handle:
        for row in csv.DictReader(handle):
            brand = clean_brand(row.get("name") or "")
            if not brand:
                continue

            generic = (row.get("generic_name") or "").strip()
            raw_form = row.get("dosage_form") or ""
            form = normalize_form(raw_form)
            strength = (row.get("strength") or "").strip()
            therapeutic_class = (row.get("therapeutic_class") or "").strip()
            is_essential = norm_generic(generic) in essential

            key = sku_key(brand, strength, form)
            existing = catalogue.get(key)

            if existing is not None:
                # A curated row wins on every reviewed field; the source only
                # fills the columns curation never carried.
                if not existing.indications:
                    existing.indications = short_indication(therapeutic_class, row.get("indications") or "")
                if not existing.manufacturer:
                    existing.manufacturer = (row.get("manufacturer") or "").strip()
                existing.is_essential = int(existing.is_essential or is_essential)
                existing._seen_forms.add(form)
                continue

            catalogue[key] = MedRow(
                brand_name=brand,
                generic_name=generic,
                default_strength=strength,
                form=form,
                aliases=aliases_for(brand, generic),
                category=category_for(therapeutic_class, generic),
                practice_types=practice_types_for(category_for(therapeutic_class, generic)),
                indications=short_indication(therapeutic_class, row.get("indications") or ""),
                manufacturer=(row.get("manufacturer") or "").strip(),
                is_essential=int(is_essential),
                priority=tier_for(form, therapeutic_class, is_essential, raw_form),
                _seen_forms={form},
            )

    # A curated row whose strength/form was hand-corrected during review may
    # not key-match any source SKU, so it would keep empty indications and
    # manufacturer. Fill those from any source row sharing the brand.
    by_brand: dict[str, tuple[str, str]] = {}
    for med in catalogue.values():
        if med.indications and med.brand_name not in by_brand:
            by_brand[med.brand_name] = (med.indications, med.manufacturer)

    for med in catalogue.values():
        if not med.indications and med.brand_name in by_brand:
            med.indications, manufacturer = by_brand[med.brand_name]
            med.manufacturer = med.manufacturer or manufacturer

    # Promote only the *curated* SKU of a pinned brand. NAPA 500 mg tablet
    # becomes tier 0; NAPA syrup, drops and suppositories stay findable at
    # their own tier rather than all seven outranking everything at once.
    for med in catalogue.values():
        if med.priority == TIER_CURATED and med.brand_name in PINNED_BRANDS:
            med.priority = TIER_PINNED

    return sorted(catalogue.values(), key=lambda r: (r.priority, r.category, r.brand_name))


# Household brands whose strength/form were verified by hand during the
# curated build. They stay tier 0 so an ambiguous source row can never
# outrank the form a chamber doctor actually prescribes.
PINNED_BRANDS = {
    "NAPA", "ACE", "MAXIM", "DOLO", "FEVASTIN", "NAPA EXTRA", "BRUFEN",
    "SETRIL", "CETZINE", "OMEE", "SERGEL", "NEXUM", "RISEK", "DOMPER",
    "MOTILIUM", "BUSCOPAN", "IMODIUM", "AMOXIL", "MOXACIL", "AUGMENTIN",
    "AZITH", "CIPROX", "METRO", "FLAGYL", "ENTAMIZOLE", "ATEN", "AMLO",
    "CONCOR", "LOSARTAN", "TELMISAT", "ATORVA", "ROSUVA", "CLOPILET",
    "ASPIRIN", "WARF", "GLUFORMIN", "DIAMET", "INSULATARD", "FOLIC ACID",
    "CALCIMAX", "FEFOL", "ZINCOVIT", "BECOSULES", "NEUROBION", "ORS",
    "ORSALINE", "ORALYTE", "SINAREST", "DECOLGEN", "MONTAIR", "VENTOLIN",
    "BETNESOL", "PREDNISOLONE", "CANDID", "BETNOVATE", "CLOBEGEN",
    "FLUCONAZOLE", "DIFLUCAN", "ORALDYNE", "DENTOGEL", "GYNOFAST",
}

HEADER = [
    "brand_name", "generic_name", "default_strength", "form", "aliases",
    "category", "practice_types", "indications", "manufacturer",
    "is_essential", "priority",
]


def write_csv(rows: list[MedRow], path: Path) -> None:
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(HEADER)
        for row in rows:
            writer.writerow([
                row.brand_name, row.generic_name, row.default_strength,
                row.form, row.aliases, row.category, row.practice_types,
                row.indications, row.manufacturer, row.is_essential, row.priority,
            ])


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--neml", type=Path, default=DEFAULT_NEML)
    parser.add_argument("--who-eml", type=Path, default=DEFAULT_WHO_EML)
    parser.add_argument("--output", type=Path, default=OUTPUT)
    args = parser.parse_args()

    rows = build_catalogue(args.source, args.neml, args.who_eml)
    write_csv(rows, args.output)

    tiers = Counter(r.priority for r in rows)
    names = {
        TIER_PINNED: "pinned", TIER_CURATED: "curated", TIER_ESSENTIAL: "essential",
        TIER_STANDARD: "standard", TIER_SPECIALIST: "specialist",
    }
    print(f"Wrote {len(rows)} medicines to {args.output}")
    for tier in sorted(tiers):
        print(f"  tier {tier} {names[tier]:11s} {tiers[tier]:6d}")
    print(f"  essential-flagged      {sum(r.is_essential for r in rows):6d}")
    print(f"  with indications       {sum(1 for r in rows if r.indications):6d}")

    return 0 if len(rows) >= MIN_ROWS else 1


if __name__ == "__main__":
    raise SystemExit(main())
