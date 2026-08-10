#!/usr/bin/env python3
"""
Build ChamberQ curated medicine CSV (~450 Bangladesh brands).

Build-time reference (not committed): BDDrugBank medex_merged.csv
https://zenodo.org/records/20749707

  python3 data/build-medicine-catalogue.py
"""

from __future__ import annotations

import argparse
import csv
import re
import sys
from collections import Counter, defaultdict
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SOURCE = Path("/tmp/bddrugbank/medex_merged.csv")
OUTPUT = ROOT / "data" / "medicine-list-draft.csv"
MIN_ROWS = 430
MAX_ROWS = 480

# Must-keep household brands — full row when MedEx match is ambiguous.
PINNED: list[dict[str, str]] = [
    {"brand_name": "NAPA", "generic_name": "Paracetamol", "default_strength": "500 mg", "form": "tablet", "aliases": "napa|paracetamol", "category": "Analgesic", "practice_types": ""},
    {"brand_name": "ACE", "generic_name": "Paracetamol", "default_strength": "500 mg", "form": "tablet", "aliases": "ace|paracetamol", "category": "Analgesic", "practice_types": ""},
    {"brand_name": "MAXIM", "generic_name": "Paracetamol", "default_strength": "500 mg", "form": "tablet", "aliases": "maxim|paracetamol", "category": "Analgesic", "practice_types": ""},
    {"brand_name": "DOLO", "generic_name": "Paracetamol", "default_strength": "500 mg", "form": "tablet", "aliases": "dolo|paracetamol", "category": "Analgesic", "practice_types": ""},
    {"brand_name": "FEVASTIN", "generic_name": "Paracetamol", "default_strength": "500 mg", "form": "tablet", "aliases": "fevastin|paracetamol", "category": "Analgesic", "practice_types": ""},
    {"brand_name": "NAPA EXTRA", "generic_name": "Paracetamol+Caffeine", "default_strength": "500 mg", "form": "tablet", "aliases": "napa extra", "category": "Analgesic", "practice_types": ""},
    {"brand_name": "BRUFEN", "generic_name": "Ibuprofen", "default_strength": "400 mg", "form": "tablet", "aliases": "brufen|ibuprofen", "category": "Analgesic", "practice_types": ""},
    {"brand_name": "SETRIL", "generic_name": "Cetirizine", "default_strength": "10 mg", "form": "tablet", "aliases": "setril|cetirizine", "category": "Allergy", "practice_types": ""},
    {"brand_name": "CETZINE", "generic_name": "Cetirizine", "default_strength": "10 mg", "form": "tablet", "aliases": "cetzine|cetirizine", "category": "Allergy", "practice_types": ""},
    {"brand_name": "OMEE", "generic_name": "Omeprazole", "default_strength": "20 mg", "form": "capsule", "aliases": "omee|omeprazole", "category": "GI", "practice_types": ""},
    {"brand_name": "SERGEL", "generic_name": "Esomeprazole", "default_strength": "40 mg", "form": "capsule", "aliases": "sergel|esomeprazole", "category": "GI", "practice_types": ""},
    {"brand_name": "NEXUM", "generic_name": "Esomeprazole", "default_strength": "40 mg", "form": "capsule", "aliases": "nexum|esomeprazole", "category": "GI", "practice_types": ""},
    {"brand_name": "RISEK", "generic_name": "Rabeprazole", "default_strength": "20 mg", "form": "capsule", "aliases": "risek|rabeprazole", "category": "GI", "practice_types": ""},
    {"brand_name": "DOMPER", "generic_name": "Domperidone", "default_strength": "10 mg", "form": "tablet", "aliases": "domper|domperidone", "category": "GI", "practice_types": ""},
    {"brand_name": "MOTILIUM", "generic_name": "Domperidone", "default_strength": "10 mg", "form": "tablet", "aliases": "motilium", "category": "GI", "practice_types": ""},
    {"brand_name": "BUSCOPAN", "generic_name": "Hyoscine butylbromide", "default_strength": "10 mg", "form": "tablet", "aliases": "buscopan", "category": "GI", "practice_types": ""},
    {"brand_name": "IMODIUM", "generic_name": "Loperamide", "default_strength": "2 mg", "form": "tablet", "aliases": "imodium|loperamide", "category": "GI", "practice_types": ""},
    {"brand_name": "AMOXIL", "generic_name": "Amoxicillin", "default_strength": "500 mg", "form": "capsule", "aliases": "amoxil|amoxicillin", "category": "Antibiotic", "practice_types": ""},
    {"brand_name": "MOXACIL", "generic_name": "Amoxicillin", "default_strength": "500 mg", "form": "capsule", "aliases": "moxacil|amoxicillin", "category": "Antibiotic", "practice_types": ""},
    {"brand_name": "AUGMENTIN", "generic_name": "Amoxicillin+Clavulanate", "default_strength": "625 mg", "form": "tablet", "aliases": "augmentin", "category": "Antibiotic", "practice_types": ""},
    {"brand_name": "AZITH", "generic_name": "Azithromycin", "default_strength": "500 mg", "form": "tablet", "aliases": "azith|azithromycin", "category": "Antibiotic", "practice_types": ""},
    {"brand_name": "CIPROX", "generic_name": "Ciprofloxacin", "default_strength": "500 mg", "form": "tablet", "aliases": "ciprox|ciprofloxacin", "category": "Antibiotic", "practice_types": ""},
    {"brand_name": "METRO", "generic_name": "Metronidazole", "default_strength": "400 mg", "form": "tablet", "aliases": "metro|metronidazole", "category": "Antibiotic", "practice_types": ""},
    {"brand_name": "FLAGYL", "generic_name": "Metronidazole", "default_strength": "400 mg", "form": "tablet", "aliases": "flagyl", "category": "Antibiotic", "practice_types": ""},
    {"brand_name": "ENTAMIZOLE", "generic_name": "Metronidazole", "default_strength": "200 mg/5ml", "form": "syrup", "aliases": "entamizole", "category": "Antibiotic", "practice_types": ""},
    {"brand_name": "ATEN", "generic_name": "Atenolol", "default_strength": "50 mg", "form": "tablet", "aliases": "aten|atenolol", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "AMLO", "generic_name": "Amlodipine", "default_strength": "5 mg", "form": "tablet", "aliases": "amlo|amlodipine", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "CONCOR", "generic_name": "Bisoprolol", "default_strength": "5 mg", "form": "tablet", "aliases": "concor|bisoprolol", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "LOSARTAN", "generic_name": "Losartan", "default_strength": "50 mg", "form": "tablet", "aliases": "losartan", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "TELMISAT", "generic_name": "Telmisartan", "default_strength": "40 mg", "form": "tablet", "aliases": "telmisat|telmisartan", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "ATORVA", "generic_name": "Atorvastatin", "default_strength": "20 mg", "form": "tablet", "aliases": "atorva|atorvastatin", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "ROSUVA", "generic_name": "Rosuvastatin", "default_strength": "10 mg", "form": "tablet", "aliases": "rosuva|rosuvastatin", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "CLOPILET", "generic_name": "Clopidogrel", "default_strength": "75 mg", "form": "tablet", "aliases": "clopilet|clopidogrel", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "ASPIRIN", "generic_name": "Aspirin", "default_strength": "75 mg", "form": "tablet", "aliases": "aspirin", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "WARF", "generic_name": "Warfarin", "default_strength": "5 mg", "form": "tablet", "aliases": "warf|warfarin", "category": "Cardiac", "practice_types": ""},
    {"brand_name": "GLUFORMIN", "generic_name": "Metformin", "default_strength": "500 mg", "form": "tablet", "aliases": "gluformin|metformin", "category": "Diabetes", "practice_types": ""},
    {"brand_name": "DIAMET", "generic_name": "Metformin", "default_strength": "500 mg", "form": "tablet", "aliases": "diamet|metformin", "category": "Diabetes", "practice_types": ""},
    {"brand_name": "INSULATARD", "generic_name": "Insulin", "default_strength": "100 IU/ml", "form": "injection", "aliases": "insulatard|insulin", "category": "Diabetes", "practice_types": ""},
    {"brand_name": "FOLIC ACID", "generic_name": "Folic acid", "default_strength": "5 mg", "form": "tablet", "aliases": "folic", "category": "Supplement", "practice_types": ""},
    {"brand_name": "CALCIMAX", "generic_name": "Calcium", "default_strength": "500 mg", "form": "tablet", "aliases": "calcimax|calcium", "category": "Supplement", "practice_types": ""},
    {"brand_name": "FEFOL", "generic_name": "Iron+Folic", "default_strength": "", "form": "tablet", "aliases": "fefol|iron", "category": "Supplement", "practice_types": ""},
    {"brand_name": "ZINCOVIT", "generic_name": "Multivitamin", "default_strength": "", "form": "tablet", "aliases": "zincovit", "category": "Supplement", "practice_types": ""},
    {"brand_name": "BECOSULES", "generic_name": "B complex", "default_strength": "", "form": "capsule", "aliases": "becosules", "category": "Supplement", "practice_types": ""},
    {"brand_name": "NEUROBION", "generic_name": "B complex", "default_strength": "", "form": "tablet", "aliases": "neurobion", "category": "Supplement", "practice_types": ""},
    {"brand_name": "ORS", "generic_name": "Oral rehydration salts", "default_strength": "", "form": "sachet", "aliases": "ors", "category": "Rehydration", "practice_types": ""},
    {"brand_name": "ORSALINE", "generic_name": "Oral rehydration salts", "default_strength": "", "form": "sachet", "aliases": "orsaline", "category": "Rehydration", "practice_types": ""},
    {"brand_name": "ORALYTE", "generic_name": "Oral rehydration salts", "default_strength": "", "form": "sachet", "aliases": "oralyte", "category": "Rehydration", "practice_types": ""},
    {"brand_name": "SINAREST", "generic_name": "Paracetamol+Phenylephrine", "default_strength": "", "form": "tablet", "aliases": "sinarest", "category": "Cold", "practice_types": ""},
    {"brand_name": "DECOLGEN", "generic_name": "Paracetamol+Chlorpheniramine", "default_strength": "", "form": "tablet", "aliases": "decolgen", "category": "Cold", "practice_types": ""},
    {"brand_name": "MONTAIR", "generic_name": "Montelukast", "default_strength": "10 mg", "form": "tablet", "aliases": "montair|montelukast", "category": "Allergy", "practice_types": ""},
    {"brand_name": "VENTOLIN", "generic_name": "Salbutamol", "default_strength": "100 mcg", "form": "inhaler", "aliases": "ventolin|salbutamol", "category": "Respiratory", "practice_types": ""},
    {"brand_name": "BETNESOL", "generic_name": "Betamethasone", "default_strength": "0.5 mg", "form": "tablet", "aliases": "betnesol", "category": "Steroid", "practice_types": ""},
    {"brand_name": "PREDNISOLONE", "generic_name": "Prednisolone", "default_strength": "5 mg", "form": "tablet", "aliases": "prednisolone", "category": "Steroid", "practice_types": ""},
    {"brand_name": "CANDID", "generic_name": "Clotrimazole", "default_strength": "1%", "form": "cream", "aliases": "candid|clotrimazole", "category": "Dermatology", "practice_types": ""},
    {"brand_name": "BETNOVATE", "generic_name": "Betamethasone", "default_strength": "0.1%", "form": "cream", "aliases": "betnovate", "category": "Dermatology", "practice_types": ""},
    {"brand_name": "CLOBEGEN", "generic_name": "Clobetasol", "default_strength": "0.05%", "form": "cream", "aliases": "clobegen", "category": "Dermatology", "practice_types": ""},
    {"brand_name": "FLUCONAZOLE", "generic_name": "Fluconazole", "default_strength": "150 mg", "form": "tablet", "aliases": "fluconazole", "category": "Antifungal", "practice_types": ""},
    {"brand_name": "DIFLUCAN", "generic_name": "Fluconazole", "default_strength": "150 mg", "form": "tablet", "aliases": "diflucan", "category": "Antifungal", "practice_types": ""},
    {"brand_name": "ORALDYNE", "generic_name": "Chlorhexidine", "default_strength": "0.12%", "form": "mouthwash", "aliases": "oraldyne", "category": "Dental", "practice_types": "dentist|general_physician"},
    {"brand_name": "DENTOGEL", "generic_name": "Metronidazole", "default_strength": "1%", "form": "gel", "aliases": "dentogel", "category": "Dental", "practice_types": "dentist|general_physician"},
    {"brand_name": "GYNOFAST", "generic_name": "Clotrimazole", "default_strength": "1%", "form": "cream", "aliases": "gynofast", "category": "Gynecology", "practice_types": "gynecologist|general_physician"},
]

# Fill remaining slots by generic (needle, category, how many brands to add).
GENERIC_FILL: list[tuple[str, str, int]] = [
    ("paracetamol", "Analgesic", 12),
    ("ibuprofen", "Analgesic", 8),
    ("diclofenac", "Analgesic", 10),
    ("mefenamic", "Analgesic", 6),
    ("aceclofenac", "Analgesic", 6),
    ("tramadol", "Analgesic", 4),
    ("naproxen", "Analgesic", 4),
    ("cetirizine", "Allergy", 10),
    ("loratadine", "Allergy", 6),
    ("fexofenadine", "Allergy", 6),
    ("levocetirizine", "Allergy", 6),
    ("desloratadine", "Allergy", 4),
    ("montelukast", "Allergy", 4),
    ("omeprazole", "GI", 10),
    ("esomeprazole", "GI", 10),
    ("rabeprazole", "GI", 6),
    ("pantoprazole", "GI", 6),
    ("domperidone", "GI", 6),
    ("loperamide", "GI", 4),
    ("hyoscine", "GI", 3),
    ("aluminium hydroxide", "GI", 4),
    ("amoxicillin", "Antibiotic", 12),
    ("clavulanate", "Antibiotic", 6),
    ("azithromycin", "Antibiotic", 10),
    ("ciprofloxacin", "Antibiotic", 10),
    ("metronidazole", "Antibiotic", 8),
    ("cefixime", "Antibiotic", 8),
    ("cefpodoxime", "Antibiotic", 6),
    ("ceftriaxone", "Antibiotic", 6),
    ("doxycycline", "Antibiotic", 6),
    ("clarithromycin", "Antibiotic", 5),
    ("levofloxacin", "Antibiotic", 5),
    ("nitrofurantoin", "Antibiotic", 3),
    ("cephalexin", "Antibiotic", 5),
    ("atenolol", "Cardiac", 6),
    ("amlodipine", "Cardiac", 12),
    ("bisoprolol", "Cardiac", 4),
    ("losartan", "Cardiac", 8),
    ("telmisartan", "Cardiac", 8),
    ("valsartan", "Cardiac", 6),
    ("olmesartan", "Cardiac", 5),
    ("hydrochlorothiazide", "Cardiac", 4),
    ("furosemide", "Cardiac", 5),
    ("spironolactone", "Cardiac", 4),
    ("atorvastatin", "Cardiac", 10),
    ("rosuvastatin", "Cardiac", 8),
    ("clopidogrel", "Cardiac", 6),
    ("isosorbide", "Cardiac", 3),
    ("diltiazem", "Cardiac", 4),
    ("metformin", "Diabetes", 12),
    ("glibenclamide", "Diabetes", 5),
    ("gliclazide", "Diabetes", 5),
    ("glimepiride", "Diabetes", 5),
    ("sitagliptin", "Diabetes", 4),
    ("empagliflozin", "Diabetes", 3),
    ("insulin", "Diabetes", 6),
    ("folic acid", "Supplement", 5),
    ("calcium", "Supplement", 8),
    ("iron", "Supplement", 8),
    ("vitamin d", "Supplement", 6),
    ("zinc", "Supplement", 5),
    ("multivitamin", "Supplement", 6),
    ("oral rehydration", "Rehydration", 6),
    ("saline", "Rehydration", 4),
    ("ambroxol", "Cold", 8),
    ("guaifenesin", "Cold", 5),
    ("dextromethorphan", "Cold", 5),
    ("phenylephrine", "Cold", 5),
    ("salbutamol", "Respiratory", 10),
    ("budesonide", "Respiratory", 5),
    ("theophylline", "Respiratory", 3),
    ("ipratropium", "Respiratory", 3),
    ("betamethasone", "Steroid", 8),
    ("prednisolone", "Steroid", 6),
    ("dexamethasone", "Steroid", 5),
    ("hydrocortisone", "Steroid", 5),
    ("clotrimazole", "Dermatology", 8),
    ("clobetasol", "Dermatology", 5),
    ("mometasone", "Dermatology", 5),
    ("fusidic", "Dermatology", 4),
    ("permethrin", "Dermatology", 3),
    ("adapalene", "Dermatology", 3),
    ("fluconazole", "Antifungal", 6),
    ("terbinafine", "Antifungal", 5),
    ("ketoconazole", "Antifungal", 5),
    ("itraconazole", "Antifungal", 3),
    ("chlorhexidine", "Dental", 4),
    ("clotrimazole", "Gynecology", 6),
    ("norethisterone", "Gynecology", 4),
    ("medroxyprogesterone", "Gynecology", 4),
    ("levonorgestrel", "Gynecology", 3),
    ("ethinylestradiol", "Gynecology", 6),
    ("tranexamic", "Gynecology", 4),
    ("paracetamol", "Pediatric", 8),
    ("amoxicillin", "Pediatric", 6),
    ("cefixime", "Pediatric", 5),
    ("salbutamol", "Pediatric", 5),
    ("cetirizine", "Pediatric", 4),
    ("chloramphenicol", "Eye/ENT", 5),
    ("ciprofloxacin", "Eye/ENT", 5),
    ("tobramycin", "Eye/ENT", 4),
    ("xylometazoline", "Eye/ENT", 5),
    ("sertraline", "Psychiatry", 5),
    ("escitalopram", "Psychiatry", 5),
    ("amitriptyline", "Psychiatry", 4),
    ("clonazepam", "Psychiatry", 5),
    ("diazepam", "Psychiatry", 5),
    ("carbamazepine", "Psychiatry", 5),
    ("valproate", "Psychiatry", 5),
    ("pregabalin", "Psychiatry", 5),
    ("levothyroxine", "Thyroid", 6),
    ("carbimazole", "Thyroid", 3),
    ("tamsulosin", "Urology", 4),
    ("finasteride", "Urology", 3),
    ("sildenafil", "Urology", 3),
    ("albendazole", "Anthelmintic", 5),
    ("mebendazole", "Anthelmintic", 3),
    ("thiocolchicoside", "Musculoskeletal", 4),
    ("eperisone", "Musculoskeletal", 4),
    ("baclofen", "Musculoskeletal", 3),
]

CATEGORY_TARGETS: dict[str, int] = {
    "Analgesic": 38,
    "Allergy": 28,
    "GI": 38,
    "Antibiotic": 48,
    "Cardiac": 48,
    "Diabetes": 28,
    "Supplement": 28,
    "Rehydration": 10,
    "Cold": 22,
    "Respiratory": 16,
    "Steroid": 14,
    "Dermatology": 24,
    "Antifungal": 10,
    "Dental": 8,
    "Gynecology": 20,
    "Pediatric": 18,
    "Eye/ENT": 14,
    "Psychiatry": 20,
    "Thyroid": 8,
    "Urology": 8,
    "Anthelmintic": 8,
    "Musculoskeletal": 10,
}

SKIP_BRAND = re.compile(
    r"(^ORS\s+[A-Z]$|ORS\s+PLUS|PLUS\s+ORS|BLEOMYCIN|CHEMO|VACCINE|"
    r"\bSR\b|\bXR\b|\bTR\b|\bDT\b|\bMUPS\b|\bDS\b|ENTERIC|INFUSION|"
    r"INJECTION|SUPPOSITORY|DROPS|PEDIATRIC DROPS)",
    re.I,
)

FORM_MAP = {
    "tablet": "tablet",
    "capsule": "capsule",
    "syrup": "syrup",
    "suspension": "syrup",
    "oral suspension": "syrup",
    "powder for suspension": "syrup",
    "cream": "cream",
    "ointment": "ointment",
    "gel": "gel",
    "injection": "injection",
    "inhaler": "inhaler",
    "metered-dose": "inhaler",
    "dry powder": "inhaler",
    "sachet": "sachet",
    "powder": "sachet",
    "drops": "drops",
    "ophthalmic": "drops",
    "mouthwash": "mouthwash",
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


def clean_brand(name: str) -> str:
    return re.sub(r"\s+", " ", name.strip()).upper()


def normalize_form(raw: str) -> str:
    key = raw.strip().lower()
    for needle, mapped in FORM_MAP.items():
        if needle in key:
            return mapped
    return "tablet"


def strength_from_row(row: dict[str, str]) -> str:
    return (row.get("strength") or "").strip()


def aliases_for(brand: str, generic: str) -> str:
    parts = {brand.lower()}
    if generic:
        parts.add(generic.lower())
    return "|".join(sorted(parts))


def practice_types_for(category: str) -> str:
    if category == "Dental":
        return "dentist|general_physician"
    if category == "Gynecology":
        return "gynecologist|general_physician"
    if category == "Pediatric":
        return "pediatrician|general_physician"
    return ""


def score_row(row: dict[str, str], category: str) -> int:
    form = normalize_form(row.get("dosage_form") or "")
    brand = clean_brand(row.get("name") or "")
    strength = strength_from_row(row).lower()
    score = 0

    if SKIP_BRAND.search(brand):
        return -100

    if form in {"tablet", "capsule"}:
        score += 20
    elif form in {"syrup", "cream", "gel", "inhaler", "sachet", "drops", "mouthwash"}:
        score += 12
    elif form == "injection" and category in {"Diabetes", "Antibiotic"}:
        score += 8
    else:
        score -= 15

    if category == "Pediatric" and form == "syrup":
        score += 8
    if category == "Rehydration" and ("ors" in brand.lower() or "saline" in brand.lower() or "rehydration" in (row.get("generic_name") or "").lower()):
        score += 15
    if category == "Eye/ENT" and form == "drops":
        score += 10

    # Prefer common strengths
    if "500 mg" in strength:
        score += 3
    if "400 mg" in strength or "40 mg" in strength or "10 mg" in strength:
        score += 2

    if len(brand) <= 12:
        score += 2

    return score


def row_to_med(row: dict[str, str], category: str) -> MedRow:
    brand = clean_brand(row["name"])
    generic = (row.get("generic_name") or "").strip()
    return MedRow(
        brand_name=brand,
        generic_name=generic,
        default_strength=strength_from_row(row),
        form=normalize_form(row.get("dosage_form") or ""),
        aliases=aliases_for(brand, generic),
        category=category,
        practice_types=practice_types_for(category),
    )


def pinned_to_med(data: dict[str, str]) -> MedRow:
    return MedRow(
        brand_name=clean_brand(data["brand_name"]),
        generic_name=data["generic_name"],
        default_strength=data.get("default_strength", ""),
        form=data.get("form", "tablet"),
        aliases=data.get("aliases", ""),
        category=data["category"],
        practice_types=data.get("practice_types", practice_types_for(data["category"])),
    )


def load_source(path: Path) -> list[dict[str, str]]:
    if not path.is_file():
        print(f"Source not found: {path}", file=sys.stderr)
        sys.exit(1)
    with path.open(newline="", encoding="utf-8") as handle:
        return list(csv.DictReader(handle))


def build_catalogue(source_path: Path) -> list[MedRow]:
    source = load_source(source_path)
    catalogue: dict[str, MedRow] = {}

    for item in PINNED:
        med = pinned_to_med(item)
        catalogue[med.brand_name] = med

    def category_count(category: str) -> int:
        return sum(1 for med in catalogue.values() if med.category == category)

    for category, target in CATEGORY_TARGETS.items():
        needles = [(n, lim) for n, cat, lim in GENERIC_FILL if cat == category]
        for needle, per_generic_limit in needles:
            if category_count(category) >= target:
                break
            matches = []
            for row in source:
                generic = (row.get("generic_name") or "").lower()
                brand = clean_brand(row.get("name") or "")
                if brand in catalogue:
                    continue
                if needle not in generic:
                    continue
                score = score_row(row, category)
                if score < 0:
                    continue
                matches.append((score, brand, row))
            matches.sort(key=lambda item: (-item[0], item[1]))
            added = 0
            for _, _, row in matches:
                if category_count(category) >= target:
                    break
                med = row_to_med(row, category)
                if med.brand_name in catalogue:
                    continue
                catalogue[med.brand_name] = med
                added += 1
                if added >= per_generic_limit:
                    break

    ordered = sorted(catalogue.values(), key=lambda r: (r.category, r.brand_name))
    return ordered


def write_csv(rows: list[MedRow], path: Path) -> None:
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(
            ["brand_name", "generic_name", "default_strength", "form", "aliases", "category", "practice_types"]
        )
        for row in rows:
            writer.writerow(
                [
                    row.brand_name,
                    row.generic_name,
                    row.default_strength,
                    row.form,
                    row.aliases,
                    row.category,
                    row.practice_types,
                ]
            )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--output", type=Path, default=OUTPUT)
    args = parser.parse_args()

    rows = build_catalogue(args.source)
    write_csv(rows, args.output)

    counts = Counter(r.category for r in rows)
    print(f"Wrote {len(rows)} medicines to {args.output}")
    for category, count in sorted(counts.items(), key=lambda item: (-item[1], item[0])):
        print(f"  {category}: {count}")

    return 0 if len(rows) >= MIN_ROWS else 1


if __name__ == "__main__":
    raise SystemExit(main())
