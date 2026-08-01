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
