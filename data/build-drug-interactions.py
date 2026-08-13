#!/usr/bin/env python3
"""
Build the ChamberQ drug interaction pair list.

Writes explicit ingredient pairs — one row per pair — so a doctor reviewing
this can read every line rather than infer it from class rules. Classes below
are only a way of avoiding typos while expanding.

DRAFT. Every row needs clinical sign-off before it is relied on; the loader
prints that on every run and `drug_interactions.reviewed_at` stays NULL until
a name is recorded against it.

Restricted to ingredients that actually appear in the ChamberQ catalogue —
verified 2026-08-12, 49 of 50 candidate drugs present.

    python3 data/build-drug-interactions.py
"""

from __future__ import annotations

import csv
from pathlib import Path

OUTPUT = Path(__file__).resolve().parents[1] / "data" / "drug-interactions.csv"

NSAIDS = ["ibuprofen", "diclofenac", "naproxen", "ketorolac", "aceclofenac", "mefenamic acid", "etoricoxib"]
SSRIS = ["sertraline", "escitalopram", "fluoxetine", "paroxetine", "citalopram"]
ACE_INHIBITORS = ["ramipril", "enalapril", "lisinopril", "perindopril"]
ARBS = ["losartan", "telmisartan", "valsartan", "olmesartan", "irbesartan"]
STATINS = ["simvastatin", "atorvastatin"]
MACROLIDES = ["clarithromycin", "erythromycin"]
AZOLES = ["itraconazole", "ketoconazole", "fluconazole"]
BETA_BLOCKERS = ["atenolol", "bisoprolol", "propranolol", "metoprolol", "carvedilol"]
RATE_CCBS = ["verapamil", "diltiazem"]
PPIS = ["omeprazole", "esomeprazole"]

# (group_a, group_b, severity, effect, action, source)
RULES: list[tuple[list[str], list[str], str, str, str, str]] = [
    (["warfarin"], NSAIDS + ["aspirin"], "avoid",
     "Greatly increased risk of gastrointestinal and other bleeding",
     "Avoid. Use paracetamol for pain instead", "Antiplatelet/anticoagulant additive bleeding risk"),
    (["warfarin"], ["ciprofloxacin", "metronidazole", "clarithromycin", "erythromycin", "fluconazole", "azithromycin"], "serious",
     "Anticoagulant effect increased; INR can rise sharply",
     "Check INR within a few days if co-prescribing is unavoidable", "CYP-mediated inhibition of warfarin metabolism"),
    (["warfarin"], ["amiodarone"], "serious",
     "Anticoagulant effect increased for weeks after starting",
     "Reduce warfarin dose and monitor INR", "CYP2C9 inhibition"),
    (["clopidogrel"], PPIS, "serious",
     "Antiplatelet effect of clopidogrel reduced",
     "Prefer pantoprazole or an H2 blocker for gastric cover", "CYP2C19 inhibition reduces clopidogrel activation"),
    (STATINS, MACROLIDES + AZOLES, "serious",
     "Statin levels rise sharply; risk of muscle damage and rhabdomyolysis",
     "Pause the statin for the antibiotic/antifungal course", "CYP3A4 inhibition"),
    (ACE_INHIBITORS + ARBS, ["spironolactone", "eplerenone", "potassium chloride", "amiloride"], "serious",
     "Risk of dangerously high potassium",
     "Check potassium and renal function; avoid in renal impairment", "Additive potassium retention"),
    (ACE_INHIBITORS, ARBS, "avoid",
     "Dual blockade of the renin-angiotensin system without added benefit",
     "Use one or the other, not both", "Combined RAS blockade increases renal failure and hyperkalaemia"),
    (["methotrexate"], NSAIDS + ["aspirin"], "serious",
     "Methotrexate excretion reduced; risk of marrow suppression",
     "Avoid, especially at higher methotrexate doses", "Reduced renal clearance of methotrexate"),
    (["methotrexate"], ["trimethoprim", "sulfamethoxazole", "co-trimoxazole"], "avoid",
     "Severe marrow suppression; both are folate antagonists",
     "Avoid the combination", "Additive antifolate effect"),
    (SSRIS, ["tramadol", "linezolid", "sumatriptan"], "serious",
     "Risk of serotonin syndrome",
     "Avoid, or counsel on agitation, tremor and fever", "Additive serotonergic effect"),
    (SSRIS, NSAIDS + ["aspirin", "warfarin", "clopidogrel"], "serious",
     "Increased risk of gastrointestinal bleeding",
     "Consider gastric protection, or an alternative analgesic", "SSRIs impair platelet aggregation"),
    (RATE_CCBS, BETA_BLOCKERS, "serious",
     "Risk of severe bradycardia, heart block and heart failure",
     "Avoid the combination; amlodipine is safer with a beta blocker", "Additive negative chronotropic and inotropic effect"),
    (["digoxin"], ["amiodarone", "verapamil", "clarithromycin", "erythromycin", "itraconazole"], "serious",
     "Digoxin levels rise; risk of digoxin toxicity",
     "Halve the digoxin dose and monitor", "Reduced digoxin clearance / P-glycoprotein inhibition"),
    (["digoxin"], ["furosemide", "hydrochlorothiazide", "indapamide"], "serious",
     "Diuretic-induced low potassium increases digoxin toxicity",
     "Monitor potassium", "Hypokalaemia potentiates digoxin"),
    (["theophylline", "aminophylline"], ["ciprofloxacin", "clarithromycin", "erythromycin"], "serious",
     "Theophylline levels rise; risk of seizures and arrhythmia",
     "Reduce theophylline dose or choose another antibiotic", "CYP1A2 inhibition"),
    (["allopurinol"], ["azathioprine", "mercaptopurine"], "avoid",
     "Severe marrow suppression",
     "Avoid; if unavoidable the thiopurine dose must be cut to about a quarter", "Xanthine oxidase inhibition blocks thiopurine breakdown"),
    (["lithium"], NSAIDS + ACE_INHIBITORS + ARBS + ["hydrochlorothiazide", "indapamide"], "serious",
     "Lithium levels rise; risk of lithium toxicity",
     "Avoid, or monitor lithium levels closely", "Reduced renal lithium clearance"),
    (["levothyroxine"], ["calcium carbonate", "ferrous sulfate", "ferrous fumarate", "calcium", "iron"], "serious",
     "Levothyroxine absorption reduced",
     "Separate the doses by at least four hours", "Chelation in the gut"),
    (["carbamazepine", "phenytoin"], MACROLIDES + AZOLES, "serious",
     "Anticonvulsant levels rise; risk of toxicity",
     "Monitor for drowsiness, ataxia and nausea", "CYP3A4 inhibition"),
    # Metformin + iodinated contrast is a real interaction but is deliberately
    # omitted: contrast is given in a radiology suite, never written on a
    # chamber prescription, so the pair could never fire and would only pad a
    # list a doctor has to read.
    (["tramadol"], ["carbamazepine"], "serious",
     "Tramadol effect reduced and seizure risk increased",
     "Choose a different analgesic", "Enzyme induction plus additive seizure risk"),
    (["spironolactone"], ["potassium chloride", "amiloride"], "avoid",
     "Risk of dangerously high potassium",
     "Avoid the combination", "Additive potassium retention"),
]


def expand() -> list[dict[str, str]]:
    seen: set[tuple[str, str]] = set()
    rows: list[dict[str, str]] = []

    for group_a, group_b, severity, effect, action, source in RULES:
        for a in group_a:
            for b in group_b:
                if a == b:
                    continue

                # Stored alphabetically so lookup never depends on typing order.
                pair = tuple(sorted((a.lower(), b.lower())))

                if pair in seen:
                    continue

                seen.add(pair)
                rows.append({
                    "ingredient_a": pair[0],
                    "ingredient_b": pair[1],
                    "severity": severity,
                    "effect": effect,
                    "action": action,
                    "source": source,
                })

    rows.sort(key=lambda r: (r["ingredient_a"], r["ingredient_b"]))
    return rows


def main() -> int:
    rows = expand()

    with OUTPUT.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=["ingredient_a", "ingredient_b", "severity", "effect", "action", "source"],
        )
        writer.writeheader()
        writer.writerows(rows)

    avoid = sum(1 for r in rows if r["severity"] == "avoid")
    print(f"Wrote {len(rows)} pairs to {OUTPUT}")
    print(f"  avoid:   {avoid}")
    print(f"  serious: {len(rows) - avoid}")
    print("  DRAFT — needs clinical sign-off before it is relied on.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
