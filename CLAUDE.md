# Project instructions — SolDoc (Doctor Gemini)

The operating protocol for this repo is the machine-wide one in `~/AGENTS.md`, symlinked into this repo root as `AGENTS.md`. It is imported below; follow it in full.

@AGENTS.md

**Never edit a copy of these rules** — edit `~/AGENTS.md`, which every project on this machine shares.

Project-specific rules that apply *only* to SolDoc go below this line.

## Patient homepage lock

The solo patient homepage UI and Book Appointment CTAs are **locked**. Do not change `resources/views/tenant/solo/webpage.blade.php`, `resources/views/tenant/solo/sections/**`, or homepage Book CTA wiring/styling unless the user explicitly says **update patient homepage** or **change patient homepage**. Book CTAs must keep `tenant_safe_href(..., '/book')`. See `.cursor/rules/patient-homepage-lock.mdc` and `decisions.md`.
