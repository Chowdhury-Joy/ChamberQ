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

## 2026-08-05
- Solo hero headline supports a mobile two-line name (newline in CMS); collapses to one line from sm breakpoint up.

## 2026-08-05
- Added static preview `public/previews/solo-homepage-v2.html` for verifying solo homepage mobile UX (hamburger, taps, shorter videos, show-more conditions) before touching locked live Blade templates.

## 2026-08-05
- Booking wizard mobile pass: plain-language `Step n of N` labels, sticky Back/Continue bar on phones (safe-area aware), 48px minimum tap targets, and a BD-tuned phone field (numeric keypad, `017XXXXXXXX` hint, separator stripping).
- Fixed booking flow indexing: `rebuildFlow()` no longer drops the type step after a choice, which had shifted indices and skipped the chamber step on clinic tenants offering both consultations and labs.
- Replaced chamber `latitude`/`longitude` with a single pasted `map_url` Google Maps link (migration backfills old coordinates into `?q=lat,lng`); added `Chamber::isGoogleMapsUrl()` host allowlist used by both the admin form rule and ticket rendering.

## 2026-08-05T15:30:52+0600
- Added `InitializeTenancyForTenantHosts` to `TenantAdminPathPanelProvider` middleware stack so Filament Livewire polls keep tenant/CSRF context, preventing “This page has expired” popups in tenant admin.

## 2026-08-05T15:35:02+0600
- Resolved persistent `419` on Filament tenant admin Livewire polls by removing competing `InitializeTenancyByPath` from `TenantAdminPathPanelProvider` so persistent requests use a single tenancy init path.

## 2026-08-05T19:00:03+0600
- Correction: restored `InitializeTenancyByPath` + `SetPathTenantUrlDefaults` on `TenantAdminPathPanelProvider` after removing path init broke Filament login URL generation (`Missing required parameter: tenant`). Livewire path tenancy stays on the global `web` `InitializeTenancyForTenantHosts` middleware.

## 2026-08-05
- Patient ticket gains a fixed serial strip that fades in once the big serial scrolls past, mirroring the serial and the number now being called (green while called); each ticket shell supplies its own header offset and the shared JS reads it from the strip's own rect.

## 2026-08-05T19:59:41+0600
- Admin panel audit remediation: removed the duplicate slot-block cancellation from `CreateSlotBlock` (it also cancelled completed visits and rendered patient names as raw HTML) leaving `SlotBlockService` as the only path; fixed `EditTenant::afterSave()` reading `wasChanged()` after a second save, which dropped marketer setup commissions; added `deleteAny()` to all four tenant policies and dropped `DeleteBulkAction` from the Chambers and Doctors tables, where Filament's bulk authorization had been bypassing the last-chamber and only-doctor rules; added index-matching `unique()` rules for tenant staff email, marketer login email, and tenant slug (plus reserved-prefix rejection). Added `tests/Feature/AdminPanelGuardrailsTest.php`.

## 2026-08-05T20:19:38+0600
- Diagnosed the recurring "This page has expired" 419 as a stale CSRF token rather than a tenancy-middleware fault (a real Livewire commit from a fresh login page returns 200; the three panels share one session cookie and Filament regenerates the token on every login). Added a global guest-only `panels::body.end` render hook in `AppServiceProvider` that reloads on 419 instead of showing the browser confirm; signed-in pages keep Livewire's prompt. Local `SESSION_LIFETIME` raised 120 → 1440.

## 2026-08-05T20:27:33+0600
- Fixed post-login 404 on the path panel: Filament's `LoginResponse` targets `Panel::getUrl()`, which falls back to `url($panel->getPath())` because no `home` route is ever registered — emitting the literal `{tenant}/admin` pattern. Added `app/Support/FilamentPanelUrl.php` (resolves a panel's dashboard route by name, keeping the domain segment multi-domain panels add) and `app/Http/Responses/FilamentLoginResponse.php`, bound to the `LoginResponse` contract for all panels; the path panel also sets `homeUrl()` for the topbar/sidebar logo. Added `tests/Feature/PathPanelLoginRedirectTest.php`.

## 2026-08-05T21:01:25+0600
- Removed session idle expiry at the owner's request: SESSION_LIFETIME set to 525,600 minutes (one year) in .env, .env.example and as the config/session.php default, SESSION_EXPIRE_ON_CLOSE=false, and AUTH_PASSWORD_TIMEOUT raised to a year so the password-confirmation window never prompts. Supersedes the 20:19 "local convenience only" framing — this is committed config for every environment. Measurement had already disproved idle expiry as the cause of the reported ~5-minute sign-outs, so the AuthDebugProvider diagnostic stays installed.

## 2026-08-05T21:41:16+0600
- Locked the no-expiry session settings against future reverts, mirroring the patient homepage lock: added `.cursor/rules/session-expiry-lock.mdc` and a **Session expiry lock** section in `CLAUDE.md`, naming SESSION_LIFETIME / SESSION_EXPIRE_ON_CLOSE / AUTH_PASSWORD_TIMEOUT and the unlock phrases. The non-standard `525600` default in `config/session.php` is called out explicitly so it is not "tidied" back to the framework's 120.

## 2026-08-05
- Clinic public site restyled to solo visual language (shell, hero banner, sections, book/ticket/portal); demo clinic homepage content expanded; shared `testimonials` section + multi-branch `location_hours` locations list.

## 2026-08-05
- Added shared health favicon (`public/icons/health-favicon.svg`) and `Tenant::faviconHref()` default for patient-site browser tabs.

## 2026-08-05T23:15:17+0600
- Added look-only Alvion-style clinic homepage preview at `public/previews/alvion-clinic-homepage.html` (dark/lime hospital layout, placeholder CTAs) for owner review before any live clinic template change.

## 2026-08-05T23:22:36+0600
- Removed Alvion preview; replaced with look-only Clireo homepage structural copy at `public/previews/clireo-homepage.html` matching https://clireo.framer.website (navy/pink, Golos Text, hero booking card, treatments, reviews, approach, doctors, FAQ).

## 2026-08-05T23:33:27+0600
- Clireo preview now uses Getwebfield spacing shell (`public/css/getwebfield-spacing.css`: stacked section padding, 1400px container, card/hero/split/stats grids) plus scroll reveals, hover lifts, approach tab fades, and `prefers-reduced-motion` respect.

## 2026-08-06T00:11:34+0600
- Clireo preview: removed before/after; kept Our Values; added Framer-like hero word blur/rise, cyan “health” underline, and dual-text hover on nav/CTAs (CSS animations for reliable hero reveal).

## 2026-08-06T12:21:46+0600
- Added ChamberQ brand mark at `public/icons/chamberq-logo.png` (300×300 PNG: C+Q monogram with medical cross).

## 2026-08-06T12:25:33+0600
- Added alternate ChamberQ logo concept at `public/icons/chamberq-logo-v2.png` (navy circular badge, C+Q with Rod of Asclepius / queue-tail motif).

## 2026-08-06T12:28:09+0600
- Added ChamberQ logo v3 at `public/icons/chamberq-logo-v3.png` (PRIMARY-inspired lime cross + navy C/Q line overlay + wordmark).

## 2026-08-06T12:29:30+0600
- Added ChamberQ logo v4 at `public/icons/chamberq-logo-v4.png` (PRIMECARE-inspired geometric navy/sky-blue flag mark + wordmark).

## 2026-08-06T12:31:43+0600
- Added ChamberQ mark-only logo at `public/icons/chamberq-mark-v5.png` (thin bracket cross, two-tone blue, no wordmark).

## 2026-08-06T12:39:30+0600
- Refined `public/icons/chamberq-logo.png` v1 mark: thinner strokes, deeper teal, simpler C+Q with minimal cross for a more premium look.

## 2026-08-06T12:41:47+0600
- Added ChamberQ logomark v6 at `public/icons/chamberq-mark-v6.png` (layered geometric heart, teal mosaic, person in negative space; no C/Q letters).

## 2026-08-06T12:45:14+0600
- Added segmented organic Q logomark at `public/icons/chamberq-mark-q-segmented.png` (mosaic tiles, nature greens, white gaps).

## 2026-08-06T12:50:11+0600
- Added v6 logomark shape variants: shield, arch, and circle at `public/icons/chamberq-mark-v6-{shield,arch,circle}.png`.

## 2026-08-06T12:53:50+0600
- Added fluid separate C+Q two-blob logomark at `public/icons/chamberq-mark-cq-fluid.png`.

## 2026-08-06T12:55:27+0600
- Simplified `chamberq-mark-v6-shield.png`: fewer larger mosaic tiles, less grid density, same shield + person silhouette.

## 2026-08-06T12:57:00+0600
- Refined `chamberq-mark-v6-shield.png`: removed central person silhouette so mosaic shield tiles are the focal point.

## 2026-08-06T12:58:30+0600
- Refined `chamberq-mark-v6-shield.png`: squat wide shield proportions, 3D beveled mosaic tiles with depth/shadow.

## 2026-08-06T13:01:46+0600
- Rebalanced `chamberq-logo.png` v1 to medium stroke weight (between original thick and over-thin refined mark).

## 2026-08-06T13:22:56+0600
- Thinned `chamberq-logo.png` v1 strokes ~40% vs prior medium-weight mark.

## 2026-08-06T13:36:30+0600
- Reverted `chamberq-mark-v6-shield.png` to prior flat mosaic version (no person, no 3D).

## 2026-08-06T13:38:25+0600
- Shortened `chamberq-mark-v6-shield.png` height: squat wide flat mosaic shield, still no person or 3D.

## 2026-08-06
- Clireo preview treatments carousel: desktop `--treat-visible` 6 (4 focused + 2 dim peeks), infinite loop via prepended/appended card clones; see decisions.md.

## 2026-08-06T18:42:00+0600
- Owner approved `public/previews/clireo-homepage.html` (CBPH on Clireo layout) as the canonical clinic-tier homepage design reference; live `tenant/webpage.blade.php` migration deferred.

## 2026-08-06T20:16:38+0600
- Patient Records Stage 1: `patients` table + `bookings.patient_id`, `PatientService`, `patients:backfill`, household picker on booking wizard and Daily Roster walk-in, Filament Patients admin (join/move visit), `LiveSessionService` `lockForUpdate()` on queue mutations, SMS name-first format; see `decisions.md` 2026-08-06T20:16:38+0600.

## 2026-08-06T20:26:30+0600
- Patient Records Stage 2: Consult Screen Filament page, `queue_runner` tenant setting, queue roles limited to doctor/staff, `TenantUserBootstrapService` + doctor login on tenant create; see `decisions.md` 2026-08-06T20:26:30+0600.

## 2026-08-06T20:31:09+0600
- Patient Records Stage 3: global `conditions` master list + `condition_usages` per doctor, `ConditionService` search/ranking, `conditions:load` CSV importer, doctor-only `GET /api/conditions/search`; see `decisions.md` 2026-08-06T20:31:09+0600.

## 2026-08-06T20:44:49+0600
- Patient Records Stage 5: Super Admin **Client Health** page (`SellerOverview` + `SellerOverviewService`) — quiet clients, go-live funnel, SMS credit warnings, overdue payment list; tenant counts only; see `decisions.md` 2026-08-06T20:44:49+0600.

## 2026-08-06T20:48:23+0600
- Patient Records Stage 6: Super Admin **Research data** page (`ResearchData` + `ResearchDataService`) at `/admin/research` — cross-tenant coded diagnosis aggregates with k-anonymity (min group 10), date range + plan tier filters; see `decisions.md` 2026-08-06T20:48:23+0600.

## 2026-08-06T20:57:04+0600
- Patient Records Stage 4 deferred: visit voice notes (`visit-audio/{tenant_id}/`), prescription photos (`visit-photos/{tenant_id}/`), manual voice transcript, doctor-auth media routes, Consult Screen catch-up banner + end-session warning; no STT or handwriting recognition; see `decisions.md` 2026-08-06T20:57:04+0600.

## 2026-08-06T21:05:01+0600
- Queue runner gains a presence fallback: `Tenant::effectiveQueueRunner()` hands call/complete controls to the other party when the configured party has no user in the practice, so the `staff` default no longer locks a staff-less solo doctor out of their own queue; `User::canOperateQueueControls()` now reads the effective runner. Exclusivity and the admin exclusion are unchanged. Covered by `tests/Feature/QueueRunnerFallbackTest.php`; see `bug_history.md` 2026-08-06T21:05:01+0600.

## 2026-08-06T23:37:36+0600
- Visit media (consultation voice notes, prescription photos) moved from the `public` disk to the `local` private disk with explicit private visibility, removing an unauthenticated web-served path to patient clinical records; `absolutePublicPath()` renamed `absolutePath()` and documented as stream-only. Caught pre-production, so no live files needed migrating. Covered by `tests/Feature/ClinicalMediaPrivacyTest.php`; see `bug_history.md` 2026-08-06T23:37:36+0600.

## 2026-08-07T01:50:29+0600
- Live Queue Control UX/UI overhaul (19-point review, all items): fixed the table ordering `CASE` that sank the currently-`called` patient below cancelled bookings, removed a hardcoded light-mode-only inline style from the End Session action, grouped the five session-lifecycle actions into one `ActionGroup`, and added a queue summary strip (`queueStats`), a skew-corrected live elapsed timer, consequence-bearing skip labels, pause reason/return-time display, per-row out-of-turn **Call now**, a TV-screen copy-link, single-session auto-selection, and a recovery banner when the browser blocks announcement audio. `LiveSessionService` gained `callSpecificPatient()` (refuses to interrupt an `in_chamber` consult; returns a jumped `called` patient to `waiting` with no skip strike) and `setAsCurrent()` now clears the consumed `retry_queue_position`; `LiveSession` gained `callTimeoutSeconds()` and `pauseEndsAt()`; `skipPatient()` now dispatches the call announcement it had always omitted. All page styling moved from no-op Tailwind utilities to a plain-CSS `lqc-` block, because this panel has no custom theme build and Filament's shipped CSS contains none of those utilities. New `tests/Feature/LiveQueueControlPageTest.php` (8 tests). See `decisions.md` and `bug_history.md` 2026-08-07T01:50:29+0600.
