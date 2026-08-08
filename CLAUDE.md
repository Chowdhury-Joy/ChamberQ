# Project instructions — SolDoc (ChamberQ)

The operating protocol for this repo is the machine-wide one in `~/AGENTS.md`, symlinked into this repo root as `AGENTS.md`. It is imported below; follow it in full.

@AGENTS.md

**Never edit a copy of these rules** — edit `~/AGENTS.md`, which every project on this machine shares.

Project-specific rules that apply *only* to SolDoc go below this line.

## Prototype phase is over (2026-08-08)

`~/AGENTS.md` §0 says a project is "a working prototype, not a hardened
production system, **unless the user says otherwise**". The owner said
otherwise on 2026-08-08: **SolDoc is now pre-production, not a prototype.**

For this repo only, §0's "do not over-invest by default" list no longer applies.
Security hardening, edge-case handling, and operational concerns (backups,
monitoring, deployment configuration, data durability) are **in scope by
default** and no longer need to be asked for.

What that changes in practice:

- Treat real patient data as if it is already live. Clinical records, phone
  numbers and names get production-grade care, not prototype-grade.
- "Works locally with green tests" is no longer sufficient evidence. Say plainly
  what has and has not been verified on a production-shaped environment.
- Prefer enforcement over convention. A rule that depends on a future author
  remembering it has already failed twice in this codebase (see the SMS segment
  entries in `bug_history.md`) — put the guard where the code converges.
- Anything unrecoverable (data loss, a patient not told their appointment is
  cancelled) outranks anything merely inconvenient.

This does **not** unlock the patient homepage or session expiry locks below;
those still need their own phrases. It also does not authorise online payment,
which remains an explicit owner decision.

## Patient homepage lock

The solo patient homepage UI and Book Appointment CTAs are **locked**. Do not change `resources/views/tenant/solo/webpage.blade.php`, `resources/views/tenant/solo/sections/**`, or homepage Book CTA wiring/styling unless the user explicitly says **update patient homepage** or **change patient homepage**. Book CTAs must keep `tenant_safe_href(..., '/book')`. See `.cursor/rules/patient-homepage-lock.mdc` and `decisions.md`.

## Session expiry lock

Sessions **never expire on idle**, by the owner's explicit decision. `SESSION_LIFETIME=525600` (one year) in `.env`, `.env.example` **and** as the `config/session.php` default, plus `SESSION_EXPIRE_ON_CLOSE=false` and `AUTH_PASSWORD_TIMEOUT=31536000`. Do not shorten these, and do not "restore" the framework's 120-minute default in `config/session.php` — it is deliberate, so a missing env var cannot reintroduce a timeout. Unlock phrases: **change session expiry** or **restore session timeout**. A security-hardening pass does not unlock. See `.cursor/rules/session-expiry-lock.mdc` and `decisions.md`.
