# Project instructions — SolDoc (Doctor Gemini)

The operating protocol for this repo is the machine-wide one in `~/AGENTS.md`, symlinked into this repo root as `AGENTS.md`. It is imported below; follow it in full.

@AGENTS.md

**Never edit a copy of these rules** — edit `~/AGENTS.md`, which every project on this machine shares.

Project-specific rules that apply *only* to SolDoc go below this line.

## Patient homepage lock

The solo patient homepage UI and Book Appointment CTAs are **locked**. Do not change `resources/views/tenant/solo/webpage.blade.php`, `resources/views/tenant/solo/sections/**`, or homepage Book CTA wiring/styling unless the user explicitly says **update patient homepage** or **change patient homepage**. Book CTAs must keep `tenant_safe_href(..., '/book')`. See `.cursor/rules/patient-homepage-lock.mdc` and `decisions.md`.

## Session expiry lock

Sessions **never expire on idle**, by the owner's explicit decision. `SESSION_LIFETIME=525600` (one year) in `.env`, `.env.example` **and** as the `config/session.php` default, plus `SESSION_EXPIRE_ON_CLOSE=false` and `AUTH_PASSWORD_TIMEOUT=31536000`. Do not shorten these, and do not "restore" the framework's 120-minute default in `config/session.php` — it is deliberate, so a missing env var cannot reintroduce a timeout. Unlock phrases: **change session expiry** or **restore session timeout**. A security-hardening pass does not unlock. See `.cursor/rules/session-expiry-lock.mdc` and `decisions.md`.
