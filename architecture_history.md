# Architecture History

## 2026-07-25
- Bootstrap Laravel 12 + Filament 4 + stancl/tenancy multi-tenant Doctor Gemini foundation (central domains vs tenant chamber hosts).

## 2026-07-27
- Solo-Doc-V1: removed payment gateway stack for pay-at-chamber; added admin/doctor/staff roles; chamber/doctor create caps on solo; lab resources gated by `lab_tests` feature.
- Phase 2: waiting-room call audio presets/custom upload and outdoor screen sound unlock UX.
- Phase 3: hardened booking/screen XSS (no unsafe innerHTML), SafeUrl for page-builder links, feature_flag string boolean parsing, schedule/lab slot validation.

## 2026-07-28
- Prepaid SMS confirmation wallet + day/week/month Operational Reports page; Live Queue heading status-aware (`called` vs `in_chamber`).

## 2026-07-31
- Hybrid path + domain tenancy: platform tenants at `/{slug}/…` on central host; optional custom domains at root; `TenantAdminPathPanelProvider`, `TenancyUrl` helpers, PWA path scope; see decisions.md.

## 2026-07-31
- Created living project memory docs (`architecture.md`, `sitemap.md`, `architecture_history.md`) and backfilled foundational decisions/bugs so future work follows the Read/Write Auto-Handoff protocol.
- Documented that online pre-payment is later-stage only and must not be suggested unless the owner asks (pay-at-chamber remains current model).
- Solo plan: `multiple_chambers` default on with 5-location cap (`Tenant::SOLO_MAX_CHAMBERS`, `ChamberPolicy`); Clinic unlimited; see decisions.md 2026-07-31 solo multi-chamber.

## 2026-07-31
- Marketer commission system: `marketers`, `discount_codes`, `billing_payments`, `commissions`; Super Admin finance resources + `/partner` marketer panel; referral session capture; see decisions.md 2026-07-31 marketer commissions.

## 2026-07-31
- Monthly commission cron moved from 1st to 7th of each month (`commissions:generate-monthly`).

## 2026-07-31
- Outdoor screen session label + tenant `eta_model` waiting-time picker (schedule guess / live average / live steady); see decisions.md adaptive waiting-time ETA.

## 2026-07-31
- Audit residuals: `HasManyByScheduleAndDate` for LiveSession bookings eager load; path SMS tickets from `APP_URL`; paid commission rows immutable on re-confirm; removed dead LiveQueue endSession + filament_path_tenant session fallback.

## 2026-08-01
- Backfilled structure missed by commits `01b1e0a` and `0fa3cb8`, which updated `decisions.md` / `bug_history.md` but not this blueprint: added shared card-grid layer (`app/Support/CardGrid.php`, `app/Filament/Concerns/UsesCardGridColumns.php`, `resources/views/components/card-grid.blade.php`, `public/css/card-grid.css`) and recorded two data-flow changes — `BookingService` is now the single write path for all bookings (phone normalisation + duplicate-serial guard inside the locked transaction), and Daily Roster writes through `LiveSessionService` so roster and Live Queue Control share one queue state machine.

## 2026-08-01
- Closed architecture.md drift after external review: documented `lockForUpdate()` booking concurrency, SMS debit-before-send + refund-on-failure (not debit-on-success), ETA buffer settings, and tenant-prefixed call-audio storage — fixes that lived in code/bug_history since the 2026-07-31 audit but were missing or wrong in the live blueprint.

## 2026-08-01 (correction to the entry above)
- The blueprint edits in the previous entry are accurate and stand — in particular `architecture.md` had wrongly said SMS credits are debited "on success" when the code debits before sending and refunds on failure. Only the attribution was wrong: those four behaviours were **never post-audit fixes and were never recorded in `bug_history.md`** (verified: 0 matches for `lockForUpdate`, `concurren`, `debit`, `refund`, `call-audio`, `ETA`, `buffer`). They are original implementations — booking row locking landed in `51bc2fc` (Phase 1 Booking Engine), SMS debit-before-send in `dcd954f` (prepaid SMS confirmations), the ETA buffer / first-N knobs in `b9e33f5` (Phase 5 live queue), and tenant-prefixed call-audio storage in `e15ac22` (Solo-Doc-V1 Phase 2). They were undocumented, not broken. Root cause: an external architecture review asserted them as unfixed risks, and the claim was written to this log without checking the repository. See the new "verify before writing to a permanent log" rule in `AGENTS.md`.

## 2026-08-01
- Domain tenant Filament panel now registers session + tenancy middleware as Livewire-persistent (matches path panel); `InitializeTenancyForTenantHosts` also resolves path tenancy from URL/referer on central hosts; local `.env` uses `SESSION_DRIVER=file` to avoid SQLite session lock 419s during polls.

## 2026-08-01
- Tenant homepage layouts (`tenant/solo/webpage`, `tenant/webpage`) now `<link>` `css/card-grid.css` so `<x-card-grid>` sections (e.g. Conditions I Treat) actually render 2-column on tablet/desktop — the shared grid CSS had only been wired into marketing home and Operational Reports.

## 2026-08-01
- Aligned Conditions I Treat with Figma (`OejxEfMPtvtG8AHNatycaW` node `26385:314`): treatment cards in a horizontal row; Including label above the grey feature list; card-grid desktop breakpoint moved 1200px → 1024px so 3-up rows fit laptop widths.

## 2026-08-01
- Solo homepage section titles unified on shared `.solo-h2` (Figma H2 = 64px desktop) in `tenant/solo/webpage.blade.php`; removed per-section ad-hoc sizes (2.35rem / 2.75rem / 3.5rem drift).

## 2026-08-01
- Full solo homepage typography scale in `tenant/solo/webpage.blade.php` (h1–body/tagline/label/nav/brand); all section blades switched from scattered Tailwind `text-*` utilities to shared `.solo-*` classes matching Figma tokens.

## 2026-08-05
- Waiting-room screen can speak the called serial: tenant `call_announce_mode` (`chime` / `voice` / `chime_and_voice`) + `call_announce_locale` (en/bn), configured in Branding → Live Queue Settings; browser SpeechSynthesis on the outdoor screen.
- Solo homepage typography/layout fix: readable heading line-heights, stacked hero credentials, full-width testimonials + card-grid, FAQ `.solo-question`, opaque sticky header, About no longer force-stretched to 85vh.
- Waiting-room voice polish: pick best English voice, speak “Serial twelve” (words not digits), delay after chime, Bangla only when a real bn voice exists.
- Waiting-room voice switched to pre-recorded English WAVs (`public/audio/announce/number-1..99.wav`) so calls sound like a token machine, not browser “ghost” TTS.
- Regenerated announce clips with Karen (not Samantha); Live Queue Control plays the same clip on Call; removed browser TTS fallback.

## 2026-08-05
- Patient ticket: Print + Save as PDF buttons open the browser print dialog; print CSS strips live-queue/share chrome and keeps serial + visit details (no server PDF library).


## 2026-08-05
- Restored solo patient homepage section markup + shell styles to the pre–layout-fix Figma version; kept `tenant_safe_href` Book CTAs and `card-grid.css` link.

## 2026-08-05
- Locked solo patient homepage UI + Book Appointment CTAs (`.cursor/rules/patient-homepage-lock.mdc`); changes require explicit “update/change patient homepage”.
