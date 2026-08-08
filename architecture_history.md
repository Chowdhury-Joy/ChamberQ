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

## 2026-08-07T00:47:39+0600
- Product renamed "Doctor Gemini" → "ChamberQ" across the live app (`config/marketing.php` default, marketing homepage copy, `.env.example`) and all project docs/tests; the codebase/repo itself is still called SolDoc. Historical entries above this line describing "Doctor Gemini" are unchanged — they reflect the name at the time they were written.

## 2026-08-07T01:13:55+0600
- Fixed the shared visit-notes form (`VisitNotesFormSchema`) crashing on open across Consult Screen catch-up notes, Complete & call next, and Mark Completed modals: `catch-up-notes-list.blade.php` now uses `replaceMountedAction()` instead of a nested `mountAction()` call (which Filament silently fails to resolve when another action modal is already open), and `visit-voice-recorder.blade.php`'s recording-timer display was changed from broken Blade interpolation (`{{ elapsed }}`) to an Alpine `x-text="elapsed"` binding. No behavior bullet in "Key Components" needed wording changes — both already described the intended (now finally working) behavior. See `bug_history.md` 2026-08-07T01:13:55+0600.

## 2026-08-07T01:50:29+0600
- Live Queue Control UX/UI overhaul (19-point review, all items): fixed the table ordering `CASE` that sank the currently-`called` patient below cancelled bookings, removed a hardcoded light-mode-only inline style from the End Session action, grouped the five session-lifecycle actions into one `ActionGroup`, and added a queue summary strip (`queueStats`), a skew-corrected live elapsed timer, consequence-bearing skip labels, pause reason/return-time display, per-row out-of-turn **Call now**, a TV-screen copy-link, single-session auto-selection, and a recovery banner when the browser blocks announcement audio. `LiveSessionService` gained `callSpecificPatient()` (refuses to interrupt an `in_chamber` consult; returns a jumped `called` patient to `waiting` with no skip strike) and `setAsCurrent()` now clears the consumed `retry_queue_position`; `LiveSession` gained `callTimeoutSeconds()` and `pauseEndsAt()`; `skipPatient()` now dispatches the call announcement it had always omitted. All page styling moved from no-op Tailwind utilities to a plain-CSS `lqc-` block, because this panel has no custom theme build and Filament's shipped CSS contains none of those utilities. New `tests/Feature/LiveQueueControlPageTest.php` (8 tests). See `decisions.md` and `bug_history.md` 2026-08-07T01:50:29+0600.

## 2026-08-07T09:57:21+0600
- Split visit completion from queue advance so the doctor can hand over the prescription while the patient is still in the room. `LiveSessionService::completeCurrentPatientWithoutAdvancing()` (same `lockSession()`/transaction as `completeCurrentPatient()`, minus `advanceQueue()`) leaves `current_booking_id` on the completed booking; Consult Screen's `completeAndCallNext` became `completeVisit`, `LiveQueueControl::nextPatient()` split into `completeVisit()`/`callNextPatientOnly()`, and Consult Screen's `callNext` was corrected to call `callNextPatient()` rather than `completeCurrentPatient()`. New patient-facing prescription share: `Prescription::shareUrl()` + `TenancyUrl::temporarySignedRoute()` (new tenant-aware signed-route helper) → unauthenticated `GET /prescriptions/{prescription}/share` (`signed`, 48h, throttled) rendering medicines/advice/prescriber/patient/date only; `Prescription::resolveDoctorChamber()` extracted so the doctor-auth print route (auth unchanged) and the share route resolve the same prescriber. Both pages gained `forgetQueueState()` because Livewire caches `getXProperty()` per request, which was making the post-action re-render show pre-action state until the next 3s poll. New `tests/Feature/CompleteVisitCallNextSplitTest.php` and `tests/Feature/PrescriptionShareLinkTest.php` (9 tests). See `decisions.md` 2026-08-07T09:57:21+0600.

## 2026-08-07T10:18:16+0600
- Two follow-ups to the two-step consult flow. Added `filament/tenant-admin/components/call-next-nudge.blade.php`, shared by Consult Screen and Live Queue Control: the "Visit completed" line turns amber with a live counter once `Booking::CALL_NEXT_NUDGE_SECONDS` (30s) passes with nobody called, so a split flow cannot quietly stall the waiting room; counted client-side because Live Queue Control has no `wire:poll`. Outdoor screen (`tenant/screen.blade.php`) now repeats the serial clip three times (`ANNOUNCE_REPEATS`, 700ms gap) with an `announceSequence` guard so a newly called serial cuts the previous sequence instead of overlapping it; the admin panel's own announcement stays at one play. See `decisions.md` 2026-08-07T10:18:16+0600.

## 2026-08-07T10:33:28+0600
- Consult Screen was read-only while the patient was in the room: the only way to prescribe was the header's "Complete visit" button, which reads as an end-of-consult action. Added `ConsultScreen::writePrescriptionAction()` and a **Write / Edit prescription** button on the patient card (visible while `in_chamber`), saving via the existing `VisitRecordService::saveForCompletedBooking()` without changing booking status. New `VisitNotesFormSchema::stateFromRecord()` hydrates a saved visit back into form state, wired through `->fillForm()` on both the write action and `completeVisit` so reopening shows existing content and completing never blanks it; new `ConsultScreen::currentVisitRecord` property backs both and is cleared by `forgetQueueState()`. Editing replaces rather than appends (the save path was already idempotent per booking). Four new tests in `CompleteVisitCallNextSplitTest`. See `decisions.md` 2026-08-07T10:33:28+0600.

## 2026-08-07T11:02:19+0600
- Post-ship UX review of the mid-consult prescription flow found two real defects and fixed both: (1) the disabled instructional hint field in `VisitNotesFormSchema` rendered blank on `writePrescriptionAction`/`completeVisit` because the new `->fillForm()` bypasses per-field `->default()` hydration — fixed by having `stateFromRecord()` always include the hint text; (2) `->label('')` on that same field never actually suppressed the label (falls back to an auto-generated one) on every action using this schema, including the pre-existing catch-up flow — fixed with `->hiddenLabel()`. Also: the patient card was calling any saved notes a "Prescription so far" / offering "Edit prescription" even with zero medicines; it now distinguishes notes-only state ("Notes so far — no medicines yet") from an actual prescription, and chips were added for Advice/Tests advised/Reports seen/Follow-up so nothing saved is invisible on the summary. See `bug_history.md` and `decisions.md` 2026-08-07T11:02:19+0600.

## 2026-08-07T12:21:37+0600
- Prescription-writing UX overhaul: `medicines` + `medicine_usages` tables, `MedicineService`, `medicines:load`, medicine search API, searchable prescription picker with per-doctor prefill and **My medicines** admin page; `VisitNotesFormSchema` rework (prescription-first, diagnosis select, follow-up chips + `follow_up_note`, frequency/duration toggle buttons, copy-last-prescription, allergy strip); voice recorder fixed (`@script`, scoped DOM); optional STT via `VisitTranscriptionService` + `POST /api/visit-media/transcribe` when tenant `voice_transcription` is on; prescription photos on private `local` disk through the form `FileUpload`; condition/medicine usage recorded only on completed bookings; Consult Screen mobile sticky actions + Filament `tenantAdmin` Vite theme; complete-visit summary with Edit (`forceForm`); fixed infinite recursion in `completeVisit` form closure (`$action->getArguments()` not `getMountedAction()`). New tests: `MedicinePickerTest`, `MyMedicinesPageTest`, `VoiceAutofillTest`; extended `CompleteVisitCallNextSplitTest`, `ClinicalMediaPrivacyTest`, `PatientRecordsStage4Test`. See `decisions.md` and `bug_history.md` 2026-08-07T12:21:37+0600.

## 2026-08-07T12:29:29+0600
- Consult Screen two-column layout wired (`cs-layout` side = context/history, main = write/complete); mobile reading order preserved via flex `order`. Follow-up on print/share now shows relative phrase + date (`VisitNotesFormSchema::followUpDisplayLabel()`); fixed `inferRelativeFollowUp()` float/int match so chip presets resolve correctly. `npm run build` added to Getting Started for the tenant admin theme.

## 2026-08-07T12:42:47+0600
- Visit-notes modals use `VisitNotesFormSchema::configureModal()` (`stickyModalHeader` + `stickyModalFooter`) on Consult Screen, Live Queue Control, Daily Roster, and `CompleteBookingWithVisitNotes::applyDoctorModal`; tenant admin theme CSS full-height modal + safe-area sticky footer on phones.

## 2026-08-07T12:49:39+0600
- Prescription dose field in visit-notes modal uses `ToggleButtons` presets (`500 mg`, `10 mg`, … + Other) like frequency/duration; `normalizeSubmission()` resolves `dose_other`.

## 2026-08-07T13:20:43+0600
- Medicine picker is a grouped dropdown (category + Your medicines + Other) instead of free search; `doctors.practice_type` and `medicines.practice_types` filter the list per specialty; admin sets practice type on Doctors resource.

## 2026-08-07T14:09:24+0600
- Added per-doctor `doctors.staff_may_enter_prescriptions` (default off) plus `User::canEnterPrescriptionFor()`, a prescription-only staff modal on Daily Roster and `VisitRecordService::saveStaffEnteredPrescription()`, so doctors who write on paper can have staff key the script in without staff gaining visit-note access.

## 2026-08-07T14:32:26+0600
- Medicine picker UX: pruned 10 junk ORS rows and moved ORS brands to Rehydration; `displayLabel()` includes generic for dropdown search; `+` create action replaces Other/custom field; prescription repeater collapses filled items; `medicines:load --prune`.

## 2026-08-07T15:39:46+0600
- Deferred the voice → field speech-to-text auto-fill: removed `app/Services/Transcription/`, `config/transcription.php`, the `POST /api/visit-media/transcribe` route and controller method, the `visit-notes-draft` Livewire listener, `VisitNotesFormSchema::mergeDraftIntoState()` / `_machine_filled`, and the `voice_transcription` tenant feature flag. Code stashed unloaded in `docs/deferred/voice-transcription/`. Plain voice notes (record, store, play back) are untouched.

## 2026-08-07T16:16:01+0600
- Linked doctor logins to prescribing profiles: nullable unique `doctors.user_id` (+ **Login account** field on the Doctors form, `User::doctorProfile`, `User::booted()` deleting hook), and `MedicineService::resolvePrescribingDoctor()` now falls back to the signed-in doctor's own profile before the solo "only doctor" shortcut — clinic doctors were getting the general-physician medicine list on My medicines and bare search. Migration backfills the pairing only where a tenant has exactly one profile and one doctor login.

## 2026-08-07T16:34:50+0600
- Started the clinic-tier homepage port to the approved Clireo reference (phase 1 of 4, shell only): extracted the reference's stylesheet and motion script into `public/css/clinic-clireo.css` + `public/js/clinic-clireo.js`, replacing every hardcoded navy/pink with tokens derived from a single `--brand` the tenant shell sets from `theme_color`; rewrote `resources/views/tenant/webpage.blade.php` as the Clireo shell (nav with Book CTA, mobile drawer, four-column footer) and dropped `h-full` from `<html>`, which blocked document scrolling under the new system. The 18 `tenant/sections/*` blades are untouched, so the shell still loads the Tailwind CDN for them.

## 2026-08-07T17:15:48+0600
- Clinic homepage port phase 2: converted the 8 section blades the Clireo reference covers (`hero`, `service_matrix`, `doctor_grid`, `about_facility`, `testimonials`, `health_insights`, `faq`, `cta_banner`) to the ported design system, with no-photo card variants (`.doc-card--initial`, `.treat-card--textonly`, name-only `.review-person`) because no block stores images of people. Added an `html.has-js` guard to the reveal rules so content is never hidden when JS does not run, and gave `testimonials` one `.review-scroller` for all widths in place of duplicated mobile/desktop markup. Nine interim Tailwind sections remain, so the Tailwind CDN stays in the shell.

## 2026-08-07T17:54:55+0600
- Clinic homepage port phase 3: ported the last nine section blades (`trust_bar` → `.marquee`, `patient_journey` / `condition_library` / `location_hours` → `.why-card` grids, `appointment_wizard` → `.book-band`, `video_gallery` → `.blog-card` + play badge, `image_carousel` → scroll-snap `.slider`, `stat_band` → `.stats-band` counters, `rich_text` → `.rich-text` prose), rewrote `image_carousel` without Alpine (the Clireo shell does not load it, which had left the slides stacked and the arrows dead), and made `stat_band` animate only plain-integer values so "50,000+" and "24/7" stay exact. Added `resources/views/tenant/solo/sections/` copies of the 12 shared blades, pinning the locked solo homepage to its pre-port markup and making `tenant/sections/` clinic-only.

## 2026-08-07T19:38:58+0600
- Clinic homepage port phase 4 (final): converted `tenant/book.blade.php`, `tenant/ticket.blade.php`, and `tenant/portal/index.blade.php` to the Clireo shell (Golos Text, `clinic-clireo.css`, `.nav`, `--brand` tokens). Shared partials `tenant.partials.booking-wizard` and `tenant.partials.ticket-body` were left untouched — each shell re-tokenizes their semantic classes in a local `<style>` block; the ticket shell aliases `--color-primary` / `--radius-md` for the partial's inline references. Added `.btn-contact--always` so book/ticket/portal nav CTAs stay visible on phones (no drawer on those pages). Removed the Tailwind CDN and `css/card-grid.css` from `tenant/webpage.blade.php` — all clinic blades now use Clireo / `.grid-cards` from `getwebfield-spacing.css`. Solo shells (`tenant/solo/*`) still load Tailwind CDN.

## 2026-08-08T00:18:18+0600
- Full-codebase audit fix batch (booking correctness + hot-path performance): added `BookingService::sessionAlreadyEndedToday()` so a session/lab window cannot be booked once its `end_time` has passed for today, mirrored client-side as `nextAvailableDate()` in `tenant.partials.booking-wizard` (replacing every date-selection use of `nextDateForDow()`). Fixed `SmsService::confirmationBody()` to use a plain ASCII hyphen instead of an em dash / middle dot — those non-GSM characters were forcing UCS-2 encoding (3 segments) while `debitOneCredit()` still took exactly 1, under-billing every clinic's SMS wallet ~3x. Converted all 26 `whereDate()` call sites on `booking_date` / `session_date` / `slot_blocks.date` (`BookingService`, `HasManyByScheduleAndDate`, `QueueStatusController`, `ScreenController`, `SlotBlockService`, `VisitRecordService`, `OperationalReportService`, `LiveQueueControl`, `ConsultScreen`, `DailyRoster`, `TodayAppointmentsWidget`, `TenantStatsOverview`) to plain `where()`, since `whereDate()` wraps the column in a SQL function and defeats the composite indexes built for the booking hot path (`bookings_roster_index`, `bookings_bookable_date_index`, `slot_blocks_tenant_date_index`). That conversion surfaced a real cross-driver storage bug — Eloquent's `'date'` cast writes a trailing `00:00:00` that SQLite (unlike MySQL/Postgres) stores literally — fixed by adding `App\Casts\DateOnly` and applying it to `Booking::booking_date`, `LiveSession::session_date`, and `SlotBlock::date` so storage is genuinely `Y-m-d` on every driver. Added regression tests: `Phase0BookingGuardsTest::test_booking_rejects_a_session_that_already_ended_today` / `test_booking_allows_a_session_still_running_today`, `SmsConfirmationTest::test_confirmation_body_stays_pure_ascii_so_one_credit_is_one_gsm_segment`. Full suite: 322 passed.

## 2026-08-08T01:14:33+0600
- Audit fix batch 2 (privacy, queue safety, accessibility, i18n). **Privacy:** `/api/patients/by-phone` now returns `Patient::maskedPickerLabel()` (initials + age) instead of real names and drops to 10 req/min; `BookingController` resolves `patient_id` against tenant + phone (replacing the un-scoped `exists:patients,id`), `patient_name` became `required_without:patient_id`, `BookingService` writes `$patient->name` onto the booking, and `PatientService::resolveForBooking()` no longer renames a patient resolved by id. Added `User::belongsToCurrentTenant()` and applied it in `VisitMediaController`, `PrescriptionController`, `MedicineController` and `ConditionController` — those routes carry no Filament panel guard and the session cookie is shared across panels, so role alone let a doctor of one practice read another's prescription photos and voice notes. Clinic hero form converted from GET to POST via new `BookingController::prefill()` (Post/Redirect/Get through the session), and the wizard now reads prefill from server-rendered `config.prefill` instead of `window.location.search`. `/lang/{locale}` replaced `back()` with a same-host check (it was an open redirect via `Referer`). **Queue:** `endSession()` returns the cancelled bookings and Live Queue Control gained `notifyCancelledAction()` + a confirmation modal naming the count and patients (reusing the slot-block WhatsApp partial, generalised with an optional `$messages` override); `bringBookingToChamber()` now refuses while another patient is `in_chamber` and returns bool so Daily Roster can say so; `reinstatePatient`/`markDelay`/`pauseSession`/`resumeSession` wrapped in the standard transaction + row lock; `resumeSession()` recovers a paused session with no `paused_at`. Fixed `LiveSession::firstOrCreate()` binding a Carbon against the now date-only `session_date` (latent bug exposed by `DateOnly`). **UX:** wizard selection cards are real `<button>`s with `.sc-title`/`.sc-sub` spans and focus rings in both shells (previously `<div onclick>`, unusable by keyboard or screen reader); `GET /book` now checks `acceptsBookings()` so a suspended tenant says so up front; patient-facing Bangla completed (22 keys → 171 total) and locked by new `PatientFacingBanglaTest`; hero/about_facility stock-photo avatar fallbacks removed (opt-in only); `sessionsData`/`labSlotsData` trimmed to the fields the JS reads; waiting-room screen `console.log`s gated behind `?debug=1`; `/storage/tenant*` gitignored (per-tenant clinical media). An uncommitted local drift in `.env.example` (`APP_URL` pointing at a dev port) was reverted in the working tree — no committed change to that file. Verified in-browser at 375/390px on both tiers plus the full suite: 333 passed. Skipped: solo Tailwind-CDN swap, which needs the patient-homepage lock phrase.

## 2026-08-08T01:46:24+0600
- First MySQL validation pass — the app had never been run against its production database engine, and could not in fact be installed on it. Three migrations were unrunnable: (1) `users` declared a foreign key to `tenants` while sorting before it, so migration #1 failed with "1824 Failed to open the referenced table" — fixed by renaming the stancl-published `2019_09_15_000010_create_tenants_table` to `0000_01_01_000000_create_tenants_table`, ahead of Laravel's own `0001_…` files, since nineteen tables reference it; (2) `booking_lab_test` formed a composite FK against `bookings (tenant_id, id)` whose unique key was only added by a later index migration ("6125 Missing unique key") — that key moved into `create_bookings_table`; (3) `add_cancellation_tracking_to_bookings_table` declared a composite `(tenant_id, slot_block_id)` FK with `nullOnDelete`, which MySQL rejects ("1830") because SET NULL applies to every column in the key — it would have nulled the booking's own `tenant_id`, so it is now a single-column FK on `slot_block_id`, which is what the feature meant. All three were invisible on SQLite, which does not resolve FK targets at CREATE time. Verified on MySQL 9.7 with the strict default `sql_mode`: 42 migrations up, full `migrate:reset` + re-migrate clean (0 tables orphaned), seeders run, all 333 tests pass on both engines, every `ONLY_FULL_GROUP_BY` reporting path (`SellerOverviewService`, `ResearchDataService`, `OperationalReportService`) returns, Bangla + 4-byte emoji round-trip intact under utf8mb4, and `booking_date` / `session_date` / `slot_blocks.date` are real `DATE` columns. Confirmed by EXPLAIN that the `whereDate` → `where` change actually reaches the index: the fixed query is a three-column covering-index lookup (cost 0.35, 1 row) where the old form dropped the date from the lookup and filtered (cost 3.47, 24 rows). Also load-tested the booking row lock for the first time — eight concurrent processes racing for one seat produced exactly one winner, seven clean `BookingUnavailableException`s, no overbooking, no duplicate serials, no InnoDB deadlock; SQLite could never have exercised this because it serialises every write. Added a `phpunit-mysql` CI job (MySQL 8.4 service, migrate + reset + re-migrate + full suite) so this cannot silently regress.

## 2026-08-08T01:58:08+0600
- Per-doctor outbound notify mix: `doctors.notify_channels` JSON (booking / late / cancel / prescription × SMS + WhatsApp). Extended `SmsService` purposes + `NotifySmsController` staff SMS routes; gated WhatsApp/Send SMS on slot-block + prescription share UIs; `markDelay()` auto-SMS when late SMS is on. Defaults preserve prior behaviour. Tests: `NotifyChannelsTest`.

## 2026-08-08T09:16:59+0600
- Made clinical-media reads disk-agnostic so moving them off the server is a config change, not a code change. `VisitMediaService::absolutePath()` (which used `Storage::disk()->path()`, and whose result the controller passed to `response()->file()`) is replaced by `exists()` + `streamResponse()`, going through `Storage::disk()->response()` / Flysystem. Both of the old calls are `local`-driver-only, so the `s3` disk already present in `config/filesystems.php` was decorative — repointing `VisitMediaService::DISK` at it would have thrown on every voice-note and prescription-photo request. Behaviour on the `local` disk is unchanged (verified: correct bytes and `Content-Type` over the real authenticated route). The disk is still `local` and nothing is backed up off the server yet; this only removes the code-level blocker.
- Fixed two `LiveSession::firstOrCreate()` calls in `LiveQueueControl` (`markLate`, `markAbsent`) that still bound a raw `Carbon` to the date-only `session_date`, missed when `App\Casts\DateOnly` landed earlier the same day; they crashed on SQLite (not MySQL) once a live session for today existed. Regression test drives both Livewire actions.

## 2026-08-08T09:53:43+0600
- Moved the "1 credit = 1 SMS segment" invariant from a convention authors had to follow into `App\Support\GsmText`, enforced at `SmsService::send()` — the single point every body converges on. The convention had failed two days after it was written: the per-doctor notify feature forwarded WhatsApp copy containing a typographic dash into an SMS body, making a 138-character cancellation notice 3 segments against 1 debited credit, and `NotifySmsController` accepted free staff text with no length cap (647 characters = 5 segments, 1 credit). `GsmText::toSingleSegment()` transliterates with `Str::ascii()` (which also renders a Bangla patient name readably instead of erasing it from their own confirmation) and truncates prose while preserving any trailing link; `segments()` is encoding-aware (160/153 GSM-7, 70/67 UCS-2); `SmsService::debitCredits()` charges the true count so the wallet cannot overstate again. One message cannot comply — a signed prescription share URL is ~181 characters, longer than a whole segment — so it keeps its context and bills 2 rather than degrading to a bare link that reads as phishing; restoring true 1-credit prescription SMS would need a short redirect route. Added `SmsSegmentBillingTest`, including a static guard that `SmsService` never uses `__()`, since a Bangla template would triple the cost of every send.

## 2026-08-08T10:36:55+0600
- Took the doctor-late SMS blast off the request thread and put a price tag on it. `LiveSessionService::markDelay()` looped the gateway once per waiting patient inside the staff member's own request (up to ten seconds each, so ~30 patients could freeze Live Queue Control for minutes) and spent a credit each without saying so. New `app/Jobs/SendDoctorLateNotices.php` does the sending, dispatched `->afterResponse()` rather than queued — the app runs no worker, so a queued job would never be delivered and the patients would go untold with no error; after-response needs no infrastructure and still frees the screen. The job is `ShouldQueue` and carries its own `tenantId` (re-initialising tenancy if needed) so switching to a real worker later is just deleting `->afterResponse()`; `QueueTenancyBootstrapper` is already enabled. Mark Late's modal now names the patient count, credits to be spent and balance left, and warns when the wallet is short. `NotifyChannelsTest`'s two markDelay tests were updated to call `$this->app->terminate()`, which is what a real request does next — the assertions themselves are unchanged and still prove the texts go out.

## 2026-08-08T11:49:22+0600
- Project reclassified from prototype to pre-production at the owner's instruction. Recorded as a project-scope override of `~/AGENTS.md` §0 in `CLAUDE.md` and a new `.cursor/rules/production-phase.mdc` (a second agent commits to this repo and would otherwise keep applying prototype-grade judgement). Hardening, backups, monitoring, durability and deployment config are now in scope by default; correctness is judged against MySQL. The patient-homepage and session-expiry locks are unaffected and still require their own unlock phrases. No code change.
- Added the `app:production-check` deploy gate (`app/Support/ProductionReadiness.php`, `app/Console/Commands/ProductionCheckCommand.php`, `ProductionReadinessTest`). First concrete act of the pre-production reclassification above: it turns the "things nobody has configured yet" list from prose in a review into a machine check that exits non-zero. Covers the settings that fail silently — `APP_DEBUG`, `APP_ENV`, `APP_KEY`, SQLite in production, localhost/non-https `APP_URL` (patient SMS ticket links are built from it), `MAIL_MAILER=log`, `SMS_DRIVER=log` while SMS is enabled — plus warnings for clinical media on the local disk and an insecure session cookie. Deliberately enabled SMS-off is treated as a valid production state, not a fault. CI additionally asserts the gate *can pass* on a production-shaped config, so it cannot drift into something no deployment can satisfy and get switched off.
