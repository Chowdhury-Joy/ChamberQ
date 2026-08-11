# Third-party data attribution

## Medicine catalogue — `medicine-list-draft.csv`

`data/medicine-list-draft.csv` is a derived work. Brand names, generic names,
strengths, dosage forms, manufacturers and therapeutic classes come from:

> **BDDrugBank: A curated corpus of 24,725 medicines marketed in Bangladesh.**
> Shazzad Hossain Mazumder, Feni University. Zenodo, 1 October 2025.
> DOI: [10.5281/zenodo.20749707](https://doi.org/10.5281/zenodo.20749707)
> <https://zenodo.org/records/20749707>

Licensed under **Creative Commons Attribution 4.0 International (CC BY 4.0)**
— <https://creativecommons.org/licenses/by/4.0/>. Redistribution, including in
modified form, is permitted with attribution, which this file provides.

BDDrugBank is itself derived from a September 2025 snapshot of
[medex.com.bd](https://medex.com.bd).

### What ChamberQ changed

The shipped CSV is not a copy of the source. It is filtered and re-shaped:

- one row per **brand + strength + form**, deduplicated from the source's
  per-product-URL rows
- `category` re-derived onto ChamberQ's own 22-category taxonomy
- `indications` reduced to the concise therapeutic class rather than the
  source's full label prose
- `priority` tiers and `is_essential` added by ChamberQ (see below)
- the 460-row curated seed in `medicine-curated-seed.csv` — hand-reviewed
  strengths and forms for household brands — overrides the source wherever
  the two disagree

Regenerate with `python3 data/build-medicine-catalogue.py` after unzipping the
source into `/tmp/bddrugbank/`. The source archive is **not** committed.

## Essential-medicine flags

The `is_essential` column is set from two public lists distributed inside the
same BDDrugBank deposit:

- **Bangladesh National Essential Medicines List (2016)** — 597 generics
- **WHO Model List of Essential Medicines (2025)** — 642 generics

These drive ranking only. They are not a clinical recommendation, and an
absent flag says nothing about whether a medicine is appropriate.

## Not included

No drug–drug interaction data ships with ChamberQ. The BDDrugBank deposit
contains a label-derived interaction graph, which was **deliberately not
imported**: it is text-mined from marketing copy, carries no severity grading,
and is not a safe basis for a clinical warning. Interaction checking, if it is
ever added, needs a licensed clinical database.
