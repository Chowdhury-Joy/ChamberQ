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

## 2026-08-08T12:31:00+0600
- Replaced the prescription share link's temporary signed URL with a stored short token, to stop the prescription SMS costing two credits. New migration `2026_08_08_120500_add_share_token_to_prescriptions_table.php` adds `share_token` (unique) + `share_token_expires_at`; `Prescription::shareToken()` mints lazily, reuses a live token so re-sending does not break the message already in the patient's phone, and rotates an expired one; `shareUrl()` now builds `/p/{token}`. New tenant route `GET /p/{token}` (throttled 30/min, registered above the `/{slug?}` catch-all) resolves it through `PrescriptionShareController::showByToken()`, which shares one private `render()` with the legacy signed `show()` so the privacy scope cannot diverge. The signed route stays registered until links already delivered expire, then is deletable. `SmsService::prescriptionBody()` traded `- view:` for a bare colon to buy margin. `GsmText`'s over-a-segment link fallback is unchanged and now unreached by any live message — kept as the net for the next long link. Verified in a browser at 375px against a real tenant, including `/demo/p/<solo token>` returning 404.

## 2026-08-08T13:47:38+0600
- Prescription medicine repeater: "Add medicine" now dispatches `repeater-collapse` so rows already filled in fold away, instead of relying on `collapsed()`, which only seeds an unmounted row's initial state.
- Prescription medicine repeater: adding a row now dispatches `prescription-medicine-added` from the add action's `after()` hook, and the repeater scrolls its last item into view — collapsing the rows above had left the new empty row off-screen.
- Written-up (collapsed) medicine rows are greyed to separate them from the row being typed into: new `cs-rx-medicines` scoping class on the repeater plus rules in `resources/css/filament/tenantAdmin/theme.css` (`--gray-100` light, translucent `white/2%` dark). Requires `npm run build`, since `public/build` is gitignored.
- Consult Screen: hid the Filament header actions below 768px so the sticky bottom bar is the only copy on phones (the two had been rendering together as duplicate Complete visit buttons), and pinned `.cs-primary-btn` to one line with `white-space: nowrap` + `flex-shrink: 0`.

## 2026-08-08T15:26:29+0600
- Visit records gained per-visit vitals (`weight_kg`, `bp_systolic`/`bp_diastolic`) and `clinical_notes`; doctor print shows diagnosis/notes/tests + vitals; patient share shows vitals only (still no diagnosis).

## 2026-08-08T15:54:34+0600
- Vitals validation moved off the save path and onto the form. `normalizeVitals()` now sanitises instead of throwing — it runs inside `submissionHasContent()`, which every completion path calls before the queue advances, so a mistyped blood pressure would have held the booking open and left the next patient uncalled. Field rules (`vitalsSection()`) show the error next to the box instead; `isUsableBloodPressure()` is the shared definition so form and service cannot drift. Added `VisitRecord::vitalsSummary()` and pointed all three views at it, fixing a Consult Screen chip that hid weight when BP was also present. Deleted the throwaway `public/previews/` mock prescriptions (realistic patient data inside the web root). Verified on MySQL 9.7 with strict `sql_mode`: `migrate:fresh --seed`, `migrate:reset` + `migrate`, and all 398 tests.

## 2026-08-08T22:47:24+0600
- Booking `ticket_url` is now relative; `bootstrap/app.php` trusts reverse proxies so absolute share/prescription URLs use the public host behind nginx/caddy instead of localhost.

## 2026-08-08T23:04:53+0600
- Outdoor TV gained a stable date-free URL (`/screen/{session}` + matching API) that always shows today; Live Queue Control Open/Copy uses it; dated `/screen/{session}/{date}` kept for old bookmarks.

## 2026-08-08T23:06:13+0600
- Outdoor screen next-up strip now shows estimated call time: API field `next_estimated_time` is the ETA engine’s actual_estimate minus 5 minutes (not the ticket buffer), rendered as “Next: #N · ~h:i A”.

## 2026-08-08T23:17:34+0600
- Outbound patient/TV URLs consolidated on `TenancyUrl::publicAbsolute()` (prescription share, SMS tickets, waiting-room bookmark, ticket Copy link); same-origin portal/ticket/screen polls and announce audio use relative paths / `public_asset()`.

## 2026-08-08T23:21:36+0600
- Ticket Copy link: visible input is ticket URL only; clipboard payload keeps ticket + map on separate lines in JS (HTML text inputs strip newlines and were gluing the two URLs).

## 2026-08-09T02:22:57+0600
- Added queue-themed ChamberQ monogram at `public/icons/chamberq-mark-queue.svg` (+ PNG exports) and app-icon variant `chamberq-logo-queue.svg` (+ PNG): C+Q with segmented serial ring, gold active token, stepped queue tail.

## 2026-08-09T02:29:56+0600
- Added token-kiosk ChamberQ mark at `public/icons/chamberq-mark-token.svg` (+ PNG exports) and app-icon variant `chamberq-logo-token.svg` (+ PNG): dispenser machine dispensing gold token numbered 10.

## 2026-08-09T02:31:13+0600
- Added C+Q token monogram at `public/icons/chamberq-mark-cq-token.svg` (+ PNG exports) and app-icon variant `chamberq-logo-cq-token.svg` (+ PNG): open C chamber frame, gold Q token with 10 and Q tail.

## 2026-08-09T02:32:54+0600
- Added three non-token C+Q marks: `chamberq-mark-cq-flow` (queue-step tail), `chamberq-mark-cq-ring` (segmented serial ring), `chamberq-mark-cq-dots` (queue dots tail); each with app-icon SVG/PNG pair.

## 2026-08-09T15:27:52+0600
- Medicine catalogue deploy gap: added `catalogues:load` (conditions + medicines CSV importers), wired into `composer setup`, and `app:production-check` blocker `MEDICINE_CATALOGUE` when the table is empty on production servers.

## 2026-08-09
- Added `NusratUrmiSeeder` for solo tenant `nusraturmi` (Dr. Nusrat Sultana Urmi / Dermavilla): dermatologist profile, two chambers, schedule, homepage CMS, domains `nusraturmi.localhost` + `drurminusrat.com`.
- Solo patient homepage hero (`tenant/solo/sections/hero.blade.php`) set to `min-h-[90vh]` per owner request.
- Solo hero portrait crop changed to `aspect-square` with `object-center` (was full-height column crop).
- Reverted solo hero `min-h-[90vh]`; kept square centered portrait only.
- Added `NusratUrmiDemoSeeder` — today's dermatology queue (8 serials, live session) for Daily Roster on tenant `nusraturmi`.
- Tenant admin Filament `brandName` now reads `Tenant::displayName()` so practice panels show the doctor's name (e.g. Nusrat Urmi) instead of ChamberQ.
- Reverted mistaken `schedule_sessions.slots_blocked` experiment; `NusratUrmiDemoSeeder` now seeds two future `SlotBlock` rows (vacation/holiday closures) for the Slot Blocks admin screen.
- Live Queue Control outdoor TV link: `TenancyUrl::screenBookmarkUrl()` keeps path-tenant host/port (`127.0.0.1:8000/{tenant}/screen/{id}`) instead of the first `*.localhost` Domain row on port 80.
- Outdoor waiting-room screen UI strings now use `lang/bn.json` via tenant `default_locale`; Nusrat Urmi demo tenant defaults to Bangla.

## 2026-08-10
- Expanded medicine catalogue from ~88 to ~460 curated Bangladesh brands (`data/medicine-list-draft.csv`); added `data/build-medicine-catalogue.py` to regenerate from pinned household brands plus BDDrugBank-scored generic fills per category budget.
- Disaster-recovery CSV backup: `DataBackupService` / `DataImportService`, chamber Admin + Super Admin Filament pages, tenant actions on Tenants, Artisan `data:backup-export` / `data:backup-import`; passwords excluded, voice/photo binaries not in ZIP.
- Prescription picker: hide already-selected brands in the repeater dropdown; dose chips from catalogue `default_strength` only; Consult Screen Complete visit green (no two blue buttons); Write prescription modal hint removed.
- Clinic relational CMS: `departments` + `blog_posts` tables, doctor website fields, public list/detail routes, homepage sections wired to DB collections.
- Date-first booking: `BookingService::openDatesFor()`, `GET /api/bookings/open-dates`, wizard `step-when`, `bookings.wants_earlier_date`, tenant admin **Waiting for earlier date** page.
- Fixed date-step advance: keep `step-when` in the wizard flow after a pick so `nextStep` reaches patient details (same index-stability rule as the type step).
- Booking details: Confirm disabled until required fields; optional `whatsapp_phone` when patient marks a different WhatsApp number.

## 2026-08-11
- Backup restore hardened: `DataImportService::assertPayloadBelongsToScope()` refuses any ZIP whose primary keys belong to another tenant or to a central (null-tenant) row — the bare-PK upsert previously let a chamber admin rewrite the Super Admin and other chambers into their own chamber; `prescription_items` validated through its parent prescription.
- Backup restore made atomic: wipe + import now share one `DB::transaction` (a failed import used to leave the chamber wiped with no rollback), and `BackupTableMap::TENANT_TABLES` reordered so `bookings` precedes `live_sessions` — the FK on `live_sessions.current_booking_id` broke both import and delete order.
- New migration `2026_08_11_130000_relink_orphaned_visit_records_and_prescriptions` — repairs visit records and prescriptions orphaned by the pre-fix patient merge, rebuilding `patient_id` from the booking.
- `PatientService::repointPatientOwnedRows()` — patient merge and move-booking now carry `visit_records` and `prescriptions` with the patient instead of letting `nullOnDelete` blank them.
- `VisitRecordService::saveForCompletedBooking(..., completingVisit:)` — medicine/condition usage learning now runs on the real Complete-visit path, where the booking is still `in_chamber` at save time; mid-consult "Write prescription" still deliberately does not record.
- `LiveSessionService::markAbsent()` now mirrors `endSession()` — completes the mid-consult patient instead of cancelling them, clears `current_booking_id`, and returns the cancelled bookings so Live Queue Control offers the same "Tell cancelled patients" WhatsApp hand-off.

## 2026-08-11
- Automatic prescribing "learning" removed (owner decision): no more `recordUsagesFromSubmission()`, no condition/medicine usage ranking, no `completingVisit` flag. `MedicineService::recordUsage()` → `saveDoctorMedicine()`, called only from My medicines and no longer touching `use_count`/`last_used_at`. Pickers order deterministically (My medicines A–Z, conditions by text match); doctor's saved entries still rank above the shared catalogue. `condition_usages` left in place with no writer; no destructive migration. Batched the per-keystroke N+1 catalogue lookup in `MedicineService::search()` while rewriting it.
- Medicine catalogue expanded from the curated 460 to the full Bangladesh market — 24,491 SKUs across 16,029 brands — from BDDrugBank v1.0.0 (CC BY 4.0, DOI 10.5281/zenodo.20749707), at the owner's explicit direction overriding the 2026-08-10 curated-catalogue decision. `medicines` gained `indications`, `manufacturer`, `is_essential`, `priority` and three indexes; rows are now keyed on brand + strength + form rather than brand alone (brand-only upserts were discarding 8,656 SKUs, mostly syrups and paediatric drops). Safety moved from exclusion to ranking: five priority tiers with the hand-verified 460 kept verbatim as a seed, and `Medicine::displayLabel()` now carries the form so same-strength SKUs are distinguishable. The prescription and My medicines pickers switched from a static grouped option list to `getSearchResultsUsing()` over `MedicineService::search()`, which orphaned `groupedSelectOptions()` and its helper chain. `data/ATTRIBUTION.md` added for the CC BY requirement.

## 2026-08-12
- Desktop Rx pad (Option B) on Consult Screen ≥1024px while `in_chamber`: full-width page (`MaxWidth::Full`), Alpine medicine table + structured C/C/H/O/O/E fields, sticky patient bar; migration adds `visit_records.chief_complaint|history|on_examination` and `prescription_items.indication|timing|instructions`; `PrescriptionTiming` closed vocabulary with bilingual print; mobile/modal path kept.
- Deleted the orphaned whole-catalogue chain from `MedicineService` — `groupedSelectOptions()`, `catalogMedicinesForPracticeType()`, `personalMedicineOptions()`, `excludeBrandsFromOptions()`, `normalizedBrandSet()`, `catalogForPracticeType()` and the `MAX_CATALOG_ROWS` (2000) cap — confirmed reachable only from each other and from tests since the pickers moved to `getSearchResultsUsing()` the day before. The cap had been a stopgap guard rail on a method nothing called; at 24,491 rows a category-grouped array of the visible catalogue is a memory/latency trap, so the method went rather than the cap. `vocabularyHints()` kept: outside that chain, bounded at 40 rows, and the restore contract for the deferred voice-transcription stash. The four tests that only existed to exercise the chain now assert the same rules through `MedicineService::search()`, the live path; the clinic-specialist rule from `bug_history.md` (2026-08-07) is now guarded by a *dermatologist-only* brand staying hidden from a dentist, because `visibleToPracticeType()` withholds nothing from a general physician and a dentist-only brand therefore stayed visible under the very fallback the old assertion claimed to catch.
- Tenant admin sidebar: unique related nav icons (no shared rectangle-stack), and desktop sidebar starts icon-only with labels on hover via `sidebarCollapsibleOnDesktop()` in `ConfiguresTenantAdminPanel`.
- Rx pad automation. Prefill became a three-layer chain — the doctor's saved default, then a per-drug catalogue default, then blank — resolved field by field inside `MedicineService::search()`; the hardcoded `'1+1+1'` / `'5 days'` literals in `MedicinePickerFields` are gone. `medicines` gained `default_frequency|default_duration|default_timing`, filled by a new `dosing-defaults:load` from `data/dosing-defaults.csv` (171 generics, 9,862 SKUs) rather than by `medicines:load`, so a BDDrugBank refresh cannot overwrite clinical judgement; the loader rejects out-of-vocabulary values, touches oral forms only, and never lets a combination inherit a single-ingredient default. `medicine_usages` gained `last_timing`. Dedupe in `search()` now prefers the doctor's usage row over the catalogue row for the same brand — the tier boost (up to 32) had been outranking the +15 usage bonus, dropping his own line for exactly the brands he had saved. Shipping these on by default, with a `hold` column instead of an approval gate, was the owner's explicit call; see `decisions.md`.
- New tables `prescription_templates` + `prescription_template_items` (doctor-owned prescription packs, written only by an explicit "Save as pack", coded diagnoses only, overwrite-by-name) with `PrescriptionTemplateService`, both added to `BackupTableMap`. `conditions` gained `default_advice` (a `{en,bn}` JSON pair) + `default_tests`, loaded by a new `condition-presets:load` from `data/condition-presets.csv` (58 diagnoses); `ConditionService::search()` now returns them so the pad can offer advice/investigation chips on diagnosis pick. **No shipped preset carries a medicine** — guarded by a test. New `App\Support\ComplaintChips` (~40 bilingual chief-complaint chips), `VisitNotesFormSchema::historySeedFromPatient()` (H/O seeded from the patient record), last-visit one-tap chips, and last visit's vitals shown as grey reference rather than pre-filled. `catalogues:load` now runs all four importers, dosing defaults last.
- CI: added a `catalogues:load` step to the MySQL job before the production-readiness gate. The gate treats an empty catalogue as a blocker and a real deploy loads it (`composer setup`), so the step was asserting a state no deployment ships in; an empty `conditions` table is now a blocker too. Verified on MySQL 8.4 locally: migrate/reset/migrate clean, gate passes with catalogues loaded, full suite 465 green on both SQLite and MySQL.
- C/C on the desktop Rx desk became a ZilSoft-style row list (complaint + per-row duration) instead of chips appending into one textarea; `ComplaintChips::parse` / `format` round-trip the plain-text `chief_complaint` column so print and the phone modal stay unchanged.

## 2026-08-12T02:12:16+0600
- Desktop Rx desk polished to Option B mockup: C/C mini-table, H/O toggles, O/E vitals table with pulse/SpO₂ columns, Inv list + InvestigationChips, Preview / Save & print / Save only (saveRxDesk returns print URL); print and patient share show pulse/SpO₂ when recorded.

## 2026-08-12T12:51:30+0600
- Rx safety + reminders batch: fixed swapped `ac`/`pc` timing shorthand; `RxSafety` warn-only duplicate-generic/allergy checks on Rx desk and modal; `VitalsTrend` SVG weight/BP charts on Consult Screen O/E; `FollowUpReminderService` + daily `follow-ups:send-reminders` (SMS 3 days before, WhatsApp staff-confirm queue), `FollowUpReminders` Operations page, `notifications` table, doctor `follow_up` notify toggles.

## 2026-08-12T13:20:29+0600
- Operational Reports summary UI is one scoped 3×3 metric grid (dropped separate status section and the page’s `card-grid.css` link); see decisions.md.

## 2026-08-12T13:25:02+0600
- Operational Reports headline grid dropped Called / In chamber / Skipped cards (six cards: Total, Completed, Still in queue, Waiting, No-show, Cancelled); mid-flow detail remains in day list and week/month tables.
- Added `drugs:coverage-report` (`app/Console/Commands/DrugCoverageReportCommand.php`), a read-only measurement answering whether drug-interaction checking is possible at all before any database is licensed. Result: 92.9% of catalogue rows fully checkable; of the remainder, 1,190 rows are devices/supplements that cannot interact and 906 rows (3.7%) are real medicines with no entry in the US drug vocabulary under any spelling (doxophylline, rupatadine, bilastine, cilnidipine, roxadustat, favipiravir and others). No interaction feature was built — the finding is recorded so the blind spot is not rediscovered later as a bug.
- `ConsultScreen::saveRxDesk()` now re-runs `RxSafety::allWarnings()` server-side on every desktop Rx pad save. The duplicate-generic and allergy rules existed twice — tested PHP reached only from the phone modal, and an untested Alpine copy that was the *only* check on the desktop pad; the two already disagreed cosmetically. The client copy stays for instant feedback; the server is now authoritative at save. Covered by `DesktopRxPadTest::test_the_server_re_checks_rx_safety_even_if_the_pad_sends_a_clashing_prescription`, which bypasses the client checks entirely.
- Shipped drug-clash warnings: `drug_interactions` table + `App\Models\DrugInteraction`, 221 ingredient pairs generated by `data/build-drug-interactions.py` from 22 clinical rules, loaded via `interactions:load` (wired into `catalogues:load`). `RxSafety::interactionWarnings()` matches on ingredients through the new shared `App\Support\DrugIngredients` (extracted from `drugs:coverage-report`, so runtime and the feasibility measurement split names identically), and `RxSafety::uncheckedMedicines()` names any line with no generic name rather than staying silent about it. Deliberately a short curated list rather than a bulk import — the coverage measurement showed 3.7% of the catalogue has no entry in any US-derived database, so an import would have under-warned on exactly the locally-marketed drugs. `reviewed_at`/`reviewed_by` remain NULL pending a named clinician's sign-off.
- Dropped `drug_interactions.reviewed_by` (owner decision: no clinician is named against clinical content anywhere in the product, since that makes one person personally answerable for a list the practice ships). The safety requirement moved from an attribution to `RxSafety::DISCLAIMER`, shown beside every warning on both the desktop pad and the phone modal and locked by `RxSafetyTest::test_every_surface_that_shows_a_warning_also_shows_the_disclaimer`. `reviewed_at` kept without a name so staleness is still answerable.

## 2026-08-12
- Moved the end-of-session "patients today without notes" catch-up banner from Consult Screen to Live Queue Control (Fill in now + patient list modal); Consult Screen no longer interrupts mid-consult; end-session toasts point at the banner on the queue page.
- Rx packs moved off the consult screen: creation, editing and deletion now live on **My medicines** (`MyMedicines::createPackAction()` / `editPackAction()` / `deletePackAction()`), and `ConsultScreen::saveRxPack()` plus the desk's Save-as-pack box were removed — the desk applies packs only (owner decision: building a named set is preparation, not consult-time work). Two wrinkles fixed while wiring it: `PrescriptionTemplateService::save()` matches on name, so renaming now deletes the original row instead of leaving a near-duplicate; and the pack list is a Livewire computed property, so writes call `forgetPacks()` or a saved pack does not appear until reload. Separately, "+ Add medicine" moved from beside the shorthand box to a full-width button under the medicine table.

## 2026-08-12T15:17:03+0600
- Daily Roster gained Mark Late (table header → `LiveSessionService::markDelay()`), keeping the Live Queue Control Session-actions entry; optional WhatsApp hand-off and SMS cost warning match the queue screen.

## 2026-08-12T15:38:21+0600
- Rx desk medicine entry fixed: per-brand dose chips now come from `MedicineService::doseOptionsForBrand()` via the new `GET /api/medicines/doses` (replacing a hardcoded 500/10/20/40/5 mg list shown for every drug), `VisitNotesFormSchema::doseOptionsForBrand()` delegates to the same lookup so the two pickers cannot drift, and the desk gained an inline per-row Reason input writing the existing `prescription_items.indication`.

## 2026-08-12T15:47:16+0600
- Consult Screen desk: Preview now opens `ConsultScreen::previewPrescriptionAction()` — a modal framing the real `prescriptions.print` route (new `resources/views/filament/tenant-admin/components/rx-preview.blade.php`) — instead of a new tab; page header actions are hidden at desk widths so Complete visit is not rendered twice.

## 2026-08-12T16:01:00+0600
- Rx desk typing box became a catalogue search (shared `applyPrefill()` / `fillOnlyStrength()` with the Brand cell, exact-match-only prefill on Enter), the pad now opens with one blank row that is dropped if untouched, and desk inputs gained their own focus style.

## 2026-08-12T16:08:33+0600
- Rx desk brand suggestion list was clipped by `overflow-x:auto` on the table wrap (CSS forces overflow-y:auto too); wrap is now `overflow: visible`, suggestions are absolutely positioned in the brand cell, and medicine API URLs are relative to the tenant host.

## 2026-08-12T16:17:49+0600
- Rx desk medicine/condition API URLs now use tenant_web_url() so local path tenancy (127.0.0.1:8000/{slug}/…) no longer 404s on bare /api/medicines/search.

## 2026-08-12T16:46:44+0600
- Medicine search/doses now prefer the catalogue SKU with complete dosing defaults; Rx desk backfills frequency/duration/timing from brand defaults on pick and dose-chip, and timing has on-focus chips.

## 2026-08-12T18:00:05+0600
- Prescription print/share restyled as a shared BD pad sheet (left clinical / right Rx); patient copy now includes full clinical + chamber; portal lists up to 2 phone-gated prescriptions as a durable backup to the 48h `/p/{token}` link.

## 2026-08-13
- Follow-up reminder batch hardened: `SendFollowUpRemindersCommand` and `FollowUpReminderService::processTenant()` now isolate failures per tenant and per visit, log skips, end tenancy in a `finally`, and return a non-zero exit when anything was skipped. One bad row previously killed the reminders for every chamber later in the cursor.

## 2026-08-13T01:35:49+0600
- Cross-chamber clinical share (Option B): `patients.share_clinical_history` + booking/walk-in checkbox (default ON); `CrossTenantClinicalHistoryService` + `SharedClinicalVisit` load other ChamberQ chambers' visit notes/Rx (no media) by phone+name with short TTL cache; Consult Screen shows Other ChamberQ clinics + merged vitals/warnings; Appendix B privacy copy updated.

## 2026-08-13T01:59:39+0600
- Optional patient NID (`patients.nid`, `BdNid`): booking/walk-in/Patients form; resolve and cross-chamber share match NID first then phone+name; never on tickets/SMS.

## 2026-08-13T02:05:58+0600
- Booking identity step simplified: short labels, no earlier-date opt-in or helper copy under WhatsApp/share/phone/NID.

## 2026-08-13T02:08:02+0600
- Booking wizard no longer shows seat counts or “Pay at the clinic” on date/identity steps; capacity still enforced silently.
## 2026-08-13T02:24:42+0600
- Booking identity: summary strip first, then Your details; Date/Change controls removed (Back to change day).

## 2026-08-13T02:36:15+0600
- Product modules (`front_door` / `live_queue` / `prescription`) in tenant `feature_flags` with Super Admin checkboxes; `EnsureTenantHasModule` route middleware; Front-door tickets omit come-around / live queue UI; Daily Roster Arrived/Done/No-show when live queue is off.

## 2026-08-13T02:39:04+0600
- Solo module list prices in `config/marketing.php` (`modules` + all-three bundle); `PlanPricingService` prices Solo from enabled modules and Clinic from Clinic tier; Super Admin preview and commission snapshots follow.

## 2026-08-13T03:17:53+0600
- Marketing pricing: Rising Star retired; homepage shows Maestro + Clinic cards and a modules à la carte table; sales name Maestro maps to internal `solo` plan.

## 2026-08-13T02:42:37+0600
- Booking identity: **Change date** link restored on the dark summary strip.

## 2026-08-13T10:20:51+0600
- Desk cashbook: `chamber_cash_entries` + `ChamberCashService`; Operations Cashbook (income/expense/net); Daily Roster Collect fee; `doctors.default_fee_taka`; included in chamber backups after bookings.

## 2026-08-13T10:23:15+0600
- Offline kit: IndexedDB bag + pending queue (`public/js/chamberq-offline.js`); `OfflineBagService` / `OfflineSyncService` / `OfflineController`; Visiting / camp page; `visit_records.offline_sync_id`; PWA shell v4; queue freeze on outage — pad save/print only, never Call next.

## 2026-08-13T10:29:34+0600
- Cashbook Waived KPI: waived rows keep the uncollected ৳ (not zero); summary `waived_amount` + count; Daily Roster shows Waived ৳….

## 2026-08-13T10:33:22+0600
- Two booking doors: `patient_accounts` + OTP (`PatientOtpService`); public `/find` directory (`DoctorDirectoryService`); `/me` serials and history (`PlatformPatientHistoryService`); marketing nav Find a doctor; reserved paths `find` / `me`.

## 2026-08-13T10:38:47+0600
- Hero banner image in the Web Pages builder is a Filament FileUpload on Laravel's public disk (`webpage-hero/{tenant_id}/`, stored as `/storage/…`); `PublicStoredImage` maps disk paths to public URLs.

## 2026-08-13T10:41:56+0600
- Latest Educational Videos (`video_gallery`) cover + MP4 uploads via `PublicMediaFields` on the public disk; `WebPage` copies `uploaded_video` onto `video_url` so the existing card layout still works.

## 2026-08-13T10:46:29+0600
- `RuntimeDirectories` creates writable `storage/framework/cache` and `livewire-tmp` on boot so PHP 8.4+ `tempnam()` does not fall back to `/tmp` and crash; Livewire temp uploads raised to 20 MB; `public/.user.ini` sets PHP upload limits.

## 2026-08-13T11:00:26+0600
- Website FileUpload: `public` disk no longer tenant-suffixed; `livewire-tmp` disk for staging; `RuntimeDirectories` repairs `public/storage` if it points at another checkout; `WebPage` promotes disk paths to `/storage/…` on save.

## 2026-08-13T11:15:22+0600
- Web Pages admin editor: collapsed lids + type/headline labels (`PageBuilderChrome`); inner pages `max-w-5xl` with gray Collapse/Expand all; homepage full width; non-sticky Save changes + beforeunload unsaved alert.

## 2026-08-13T12:08:55+0600
- Portal phone lookup lists every prescription with medicines (removed the 2-item `PORTAL_PRESCRIPTION_LIMIT` cap).

## 2026-08-13T12:48:03+0600
- Replaced Stancl's tagged-only `CacheTenancyBootstrapper` with `App\Tenancy\CacheTenancyBootstrapper` so file/database cache stores prefix keys instead of throwing.

## 2026-08-13T13:09:33+0600
- Added consult-pad mic-to-prescription: `PrescriptionDictationService` + `POST /api/prescriptions/dictate` + Groq config. Browser speech, no audio stored; catalogue-matched draft only.
- Rx pad speed pass: the doctor's own `MedicineUsage` shortlist now appears as one-tap chips above the medicine table (alphabetical, capped at 8, personal and excluding hidden entries), each tap filling the row from his saved line; medicine rows gained ↑/↓ reordering; Enter moves through dose/frequency/duration/reason cells and adds a row off the end. "Packs" relabelled **Use a pack** and its panel chrome dropped so nothing on the consult screen reads as a place to build one, and the Add medicine control became a real button with proper spacing instead of a dashed placeholder.
- Rx desk visual pass: table type raised to 14px with the brand at 15px (the bold on `.cs-rx-desk__brand` had never applied — `.cs-rx-desk__table input { font: inherit }` out-specified it and `font` shorthand resets weight); the ℞ card given a tinted header and stronger border so it out-ranks the eight cards around it; row hover added for tracking across seven columns; the six left-hand cards merged into one continuous sheet in CSS with no markup change; chips that *insert* (Yours, packs) marked `--add` with a leading + to distinguish them from chips that *toggle*. Action bar cut from four buttons to two: **Save only** removed, and **Complete visit** returned to the page header, which also removed the ≥1024px rule hiding `.fi-header-actions-ctn` and reduced three interacting breakpoint rules to a clean pair.

## 2026-08-13T18:09:24+0600
- Prescription timing on print/share now keeps `HtmlString` through `PrescriptionItem::timingBilingualLabel()` so Bangla/English labels render instead of escaped `<span>` tags.

## 2026-08-13T18:25:48+0600
- Correction: an 18:03:32 line on Bangla-focused print/share was overwritten by the 18:09:24 timing entry; that print behaviour still stands in `architecture.md` and `decisions.md`. Starter diagnosis advice now shows as a tap chip in the Advice card and is rehydrated from the coded condition so a pad save does not hide it.

## 2026-08-13T20:18:58+0600
- Doctor print can skip the ChamberQ letterhead (`?paper=1` / desk **My paper** tick, ~40mm top gap); patient share and portal stay headed. Offline print follows the same tick; PWA shell cache bumped to `clinic-shell-v5`.

## 2026-08-13T20:32:16+0600
- Rx desk speed pass: **Why?** typeahead (`IndicationSuggestions` + conditions search), advice chips + browser ★ (`AdviceChips`), Temp °F column + finding chips (`FindingChips`), H/O More gained COPD/Allergy, medicine rows gained a drag handle (↑↓ kept). PWA shell cache bumped to `clinic-shell-v6`.

## 2026-08-13T20:45:05+0600
- O/E on the desktop Rx pad is a wrapping vitals line (Wt / BP / P / SpO₂ / T) instead of a five-row table; finding chips and Other findings sit tight underneath. Same fields, grey last-visit, never pre-filled.

## 2026-08-13T21:07:28+0600
- Desktop Rx pad patient strip sticks at `top: 4rem` / z-index 20 so it sits under Filament's topbar instead of covering the menu and Complete visit.

## 2026-08-13T21:11:44+0600
- Correction: the 21:07:28 strip offset was source-only until `npm run build`; compiled theme now includes it, and `.fi-topbar-ctn` is z-index 40 so the menu bar always paints above the strip.

## 2026-08-13T21:43:23+0600
- Tenant admin chrome: no global topbar; sticky content header (Geist, back on Create/Edit, Save/Create as the header CTA, Delete in the form footer); full-width content; nav grouped Operations / Website / Settings. Collapsed icon sidebar and Filament Blue kept. Rx pad strip still sits at `top: 4rem` under the new header.

## 2026-08-13T22:08:16+0600
- Visit report photos: `visit_records.report_photo_paths` JSON on the private `visit-reports/{tenant_id}/` disk; Reports moved to the left of the Rx pad with an image upload; Voice/photo chip removed from the pad; staff Daily Roster entry may attach report photos but still cannot write `reports_seen`.

## 2026-08-13T22:34:01+0600
- Stashed consult-pad voice-to-writing: `PrescriptionDictationService`, `PrescriptionDictationController`, `config/groq.php` and the Mic markup moved to `docs/deferred/prescription-dictation/` (unloaded `.stash` files). No Groq calls; typing the Rx is unchanged. 20-second visit voice notes stay.

## 2026-08-13T23:35:10+0600
- Rx pad now saves itself: `ConsultScreen::autosaveRxDesk()` (silent sibling of `saveRxDesk()`), Alpine `x-effect` debounce + flush on click-away/visibilitychange, `beforeunload` guard, and an Unsaved/Saving/Saved badge — Complete visit used to close a visit on whatever was last stored, which was usually nothing.
- Pad made `wire:ignore`, keyed on the booking alone, with both desk saves `#[Renderless]`: a stable key alone turned the post-save remount into a morph that re-ran `x-data` and killed every `x-show` on the pad.
- Rx pad breakpoint dropped 1024px → 768px, columns stacking below 1024px with touch-sized controls, so tablets get the desk instead of the far smaller phone modal; desk follow-up gained 3 months and Pick a date.
- Printed sheet: medicines numbered, and `app/Support/PrescriptionQuantity.php` prints a total dose count when frequency × duration multiplies out cleanly (silent for SOS/Continue).
- Patient share copy made phone-first (card per medicine below 640px) with `app/Support/DoseSchedule.php` writing `1+0+1` out in Bangla, plus a WhatsApp forward gated to the share-link routes so the portal's phone-carrying URL is never forwarded.

## 2026-08-14T01:12:58+0600
- Replaced every remaining website image **URL** field in the tenant admin with the `PublicMediaFields` uploader (gallery slides, testimonial avatars, FAQ panel, About Practice cards, blog, departments, doctor photos, branding logo/favicon); model `saving` hooks now promote the disk path to `/storage/…` before `SafeUrl` scrubs it, the shared image field no longer accepts SVG, and `PublicStoredImage::toPublicPath()` refuses to prefix `/storage/` onto a scheme-carrying value.

## 2026-08-14
- Cross-chamber clinical history now requires age agreement (or NID) on top of phone + name, and rejects a conflicting recorded sex; fails closed when no age is known. Booking wizard asks for age in whole years (optional) and carries it through BookingController → BookingService → PatientService, filling a missing age without overwriting a chamber-recorded one.
- Migration `2026_08_13_235900_reset_share_clinical_history_for_pre_consent_patients` clears the sharing flag for patients created before the consent checkbox existed; `down()` intentionally no-ops.
- `VisitMediaService::isOwnedMediaPath()` generalises the report-photo guard to voice and photo, applied in `VisitMediaController` (defence in depth — `FilesystemTenancyBootstrapper` already roots the `local` disk per tenant).
- `VisitRecord::markAsForeignChamberRecord()` + a `saving` guard: rows loaded from another chamber cannot be saved back with their stripped media paths.
- `nid` removed from `PatientAccount::$fillable` — it is accepted as proof of ownership and must never be self-asserted.

## 2026-08-14T12:34:23+0600
- Solo module one-time setup prices changed in `config/marketing.php`: Front door ৳3,000, Live queue ৳12,000, Prescription ৳2,000; monthly and Maestro/Clinic bundle stickers unchanged.

## 2026-08-14T12:59:37+0600
- Prescription module re-priced in `config/marketing.php` and `.env.example`: setup ৳2,000 → ৳5,000, monthly ৳0 → ৳250. Maestro bundle unchanged (৳15,000/৳3,000 — now ৳5,000 off the ৳20,000 unit setup sum). Launch offer updated to "Prescription free for life" covering both setup and monthly for website signups before 31 August; marketing homepage, Maestro proposal, Super Admin helper text, and sales docs updated to match.

## 2026-08-14T13:38:25+0600
- Visiting / camp patient handoff is the printed sheet only. Print / WhatsApp / SMS stay on Live Queue Control and Consult Screen after complete; upload does not send SMS. Dropped the leftover “SMS waits until then” copy on that page.

## 2026-08-14T14:15:38+0600
- Prescription module one-time setup re-priced ৳5,000 → ৳2,500 in `config/marketing.php` default and `.env.example` (`MARKETING_MODULE_PRESCRIPTION_SETUP`); monthly stays ৳250. Maestro bundle unchanged (৳15,000/৳3,000) — the setup discount vs the ৳17,500 unit sum is now ৳2,500. Offer copy, Super Admin helper text, Maestro proposal (`.md` + `.html`), Client Guide, Marketing Playbook, tests (`ModulePricingTest` prescription-only 2500/250, website+prescription 5500/1250, tenant-read 2500/250; `MarketingLandingPageTest` offer line) updated to match.

## 2026-08-14T22:39:27+0600
- Super Admin billing cashbook: Maestro labels, config-driven module prices, launch-offer ticks on `tenants`, live list/due + partner commission preview, pending commission refresh on re-price, and Confirm 12 months prepaid. Migration `2026_08_14_223000_add_billing_offer_flags_to_tenants`.

## 2026-08-14T22:39:16+0600
- Super Admin billing ledger: `tenants.offer_prescription_lifetime_free` and `offer_prepaid_year_setup`; `PlanPricingService::quote` / `quoteForTenant`; pending commission refresh; `confirmYearPrepaid` (12 monthly rows). Tenant form shows Maestro, config prices, offer ticks, and partner commission preview. Doctors list and partner referred list show Maestro/modules/due amounts.

## 2026-08-14T23:45:02+0600
- Super Admin panel UX: Restore/Delete behind a **Dangerous** overflow; platform restore defaults to dry-run with loading buttons; dashboard finance → totals → latest 8 tenants (Maestro labels, amber/sky colours, no AccountWidget); Tenants first under Platform; Client Health names link to tenant edit (`seller-client-cell` partial); Copy referral link uses the clipboard.

## 2026-08-15T00:23:45+0600
- Super Admin panel UX follow-up: restored the deleted `.backup-card-body` padding rule (both cards were rendering edge-to-edge); keyed the platform restore submit on the dry-run state so the danger colour actually paints, plus a red callout and `wire:confirm` naming what gets wiped; Tenants list row actions moved into an `ActionGroup` with the finance columns toggled off by default and the name column wrapping, so Edit is reachable without horizontal scroll at 1280 and 375.

## 2026-08-15T02:15:06+0600
- Super Admin Create Tenant keeps field defaults via `fillPartially` (a full `fill()` had wiped Plan Tier / billing / SMS / theme / locale). Tenant form is one column of fieldsets; Amount preview is labeled large/semibold figures (empty snapshot inputs removed); module unit prices sit on each checkbox. Research date/plan filters use Filament input chrome so they match the 36px panel controls.

## 2026-08-15T02:49:20+0600
- Extracted Filament admin chrome into `resources/css/filament/shared/admin-shell.css`; tenantAdmin/theme.css now imports it then keeps `.cs-*` consult/Rx-desk styles. Super Admin gained `superAdmin/theme.css` plus the tenant-admin shell (no topbar, collapsed sidebar, outlined ungrouped row actions). Custom `amber`/`sky` panel colours removed.

## 2026-08-15T09:49:04+0600
- Sidebar expand/collapse toggle is a hamburger (`Heroicon::OutlinedBars3`) on every admin panel via `UsesHamburgerSidebarToggle`; nav-group chevrons unchanged.

## 2026-08-15T10:30:45+0600
- Pocket buzz on the live-queue ticket: `booking_push_subscriptions`, `SendQueueApproachPushes` (afterResponse, Bangla, no SMS/WhatsApp), `POST /api/queue/{booking}/push`, service worker `push` handler (`clinic-shell-v7`).

## 2026-08-15T13:06:00+0600
- Honest late sitting: `SittingPrompt` service + sticky callouts on Daily Roster / Live Queue / Consult Screen; `LiveSession::effectiveStartTime()` clock rule; Start confirmation modals; Mark Late on `delayed` sittings (Add time, larger total only).

## 2026-08-15T13:40:39+0600
- `LiveSessionService::markDelay()` refuses a delay total that is not larger than what was already announced, so the “Add time only” rule cannot be skipped at the service.

## 2026-08-15T13:55:38+0600
- Five queue honesty batch: `ScheduleSessionPace` + sitting-form minutes-each hint; `PublishedComeAround` on SMS/wizard/ticket; `walk_in_overflow_cap` / `is_overflow` with staff `allowOverflow`; pause blocks Call next (Doctor stepped out); `idle_after_start` sticky; staff pocket buzz (`staff_push_subscriptions`, `SendStaffSittingPromptPushes`).

## 2026-08-15
- Offline TV last-known-good + self-hosted fonts + SW v8; offline Call next replay on this computer (`OfflineQueueBagService`, `chamberq-queue-offline.js`, `offline_queue_events`); Bangla tenant admin panel from chamber language (`ConfiguresTenantAdminPanel` locale, `BanglaStaffPanelTest`).

## 2026-08-15T14:26:41+0600
- Marketing `/` served by `MarketingController` (sanitised view payload); shared `LocaleController` for `/lang/{locale}`; clinic Website nav group via `getNavigationGroup()`; branded HTML error pages under `resources/views/errors/`.

## 2026-08-15T14:46:39+0600
- Production audit: `PlatformPatientHistoryService` fails closed (an unfiltered `select * from bookings` was reachable); new `App\Support\PushEndpoint` gates both Web Push subscribe routes against SSRF; `InitializeTenancyForTenantHosts` limits its Referer fallback to same-host `livewire/*` and no longer escalates a DB fault to a 500; clinic body/bio sanitised at render as well as save; patient logout invalidates the session; OTP rows pruned per phone; `2026_08_15_160000_add_phone_lookup_indexes` adds phone-leading indexes on `bookings` and `patients` for the cross-tenant lookups; hardcoded-path debug writers removed from five files.

## 2026-08-15T21:12:17+0600
- Booking confirmation SMS moved off the patient's request into `SendBookingConfirmation` (`->afterResponse()`, skips a cancelled serial, swallows gateway failures); `HttpSmsGateway::redact()` strips the api_key/sender out of a gateway error before it reaches `sms_messages.error` and the log; sign-out diagnostics re-gated on new `config/diagnostics.php` because `env()` is null under `config:cache`; new `tests/Feature/SourceHygieneTest.php` fails CI on absolute developer paths and on `env()` outside `config/`; froze the clock in `FiveQueueHonestyTest`, which failed on unmodified code on any run after 20:00.

## 2026-08-15T22:37:54+0600
- Tenant admin panels run `Localization` after tenancy init so chamber language and **Switch to Bangla** actually apply; `LocaleController` returns signed-in chamber staff without a Referer to `/admin` instead of the public homepage.

## 2026-08-15T23:46:24+0600
- Tenant admin desk chrome (sidebar, Live Queue, Daily Roster, dashboard widgets) goes through `__()` via `TranslatesStaffChrome` / `TranslatesResourceChrome`; `StaffDeskBanglaTest` guards `lang/bn.json`.

## 2026-08-16T00:01:07+0600
- Removed `TranslatesStaffChrome` / `TranslatesResourceChrome`; added `EnglishFilamentLoader` so Filament vendor chrome stays English. Sidebar, titles, and buttons stay English; desk reading copy still uses `__()`.

## 2026-08-16T00:13:24+0600
- Doctor panel home is Consult Screen: `FilamentPanelUrl::home()` + tenant `Dashboard` redirect/hide-nav; new `app/Filament/TenantAdmin/Pages/Dashboard.php`. Staff and the account owner still land on the stats dashboard.

## 2026-08-16T00:46:49+0600
- Front-desk lists (Daily Roster, Slot Blocks, Waiting for earlier date, Follow-up reminders) gated by `User::canWorkDesk()` / `Tenant::hasStaffLogin()` so they sit on the staff menu, not the doctor's, unless the chamber has no staff login.

## 2026-08-16T01:21:54+0600
- Chambers and Schedule Sessions gated by `User::canManageSittingSetup()` (staff / admin / solo-doctor fallback). Operational Reports opened to every chamber login via `User::canViewOperationalReports()`. Doctors resource stays `canManageOps`.

## 2026-08-16T01:39:02+0600
- Operational Reports closed to staff: `User::canViewOperationalReports()` is admin and doctor only (`canManageOps`). Chambers and sitting hours stay on the staff menu.

## 2026-08-16T01:50:11+0600
- Patient Collect fee is predefined only: `doctors.extra_fees` plus `chamber_cash_entries.fee_type`; staff cannot type a patient amount.

## 2026-08-16T02:27:25+0600
- Repeating serials: `RepeatBookingService`, `doctors.allows_repeat_serials`, `bookings.repeat_series_id`; Daily Roster Repeat sitting / Cancel later sittings.

## 2026-08-16T10:06:49+0600
- Chamber admin (`User::ROLE_ADMIN`) can open desk lists, queue controls, Consult Screen, and visit notes; `User::landsOnConsultScreen()` keeps the doctor's login home on Consult Screen.

## 2026-08-16T10:31:09+0600
- Chamber admin no longer opens Consult Screen, visit notes, Live Queue, follow-up reminders, or waiting-for-earlier-date; Daily Roster and practice setup stay.

## 2026-08-16T13:08:30+0600
- Daily Roster walk-in household picker imports `App\Services\PatientService` (bare `PatientService` in the Pages namespace 500s as a missing Filament page).

## 2026-08-16T17:14:10+0600
- Stations opt-in module: `schedule_sessions.kind`, `fee_catalog_items`, split till columns on `chamber_cash_entries`, voucher/procedure fields on `bookings`, `schedule_session_overrides`, `StationsTillService` / handoff / morning-count jobs, Fee catalogue + day-override Filament resources, Daily Roster Stations Collect fee and procedure workflow.

## 2026-08-16T17:19:59+0600
- First Stations client seed: `PainSolutionStationsSeeder` (Dr. Moin Uddin / Pain Solution Center, two branches, three room lines, fee catalogue); documented in `architecture.md` Getting Started.

## 2026-08-16T17:36:48+0600
- Cashbook Add income/expense: **Cash + online** split fields with online-method picker (bKash, Nagad, bank, Bangla QR, card, other); renamed mobile labels to online across Stations Collect fee.

## 2026-08-16T18:14:03+0600
- Referrals + HR opt-in modules: `referring_doctors`, `referral_commissions`, `bookings.referring_doctor_id`, HR tables (`employees`, attendance, leave, payroll); `ReferralCommissionService`, `HrPayrollService`, Filament resources, Pain Solution seeder enables both.

## 2026-08-16
- Made the two cashbook-writing money paths atomic: `ReferralCommissionService::markPaid()` now re-reads the selected commissions under a row lock inside one transaction and pays only those still pending (a stale Filament bulk selection could pay a referring doctor twice), and `HrPayrollService::recordSalaryPayment()` now writes its cash entry and payroll row in one transaction so a duplicate pay period rejected by the unique index cannot leave an orphan salary expense.

## 2026-08-16T23:47:15+0600
- Clinic public nav: `clinic_nav_items()` + `tenant.partials.clinic-header` (real `/departments` `/doctors` `/blog` + extra WebPages with path-tenant prefix; inner clinic pages get the same drawer). `.fx-btn-track` no longer self-clips, so nav hover can slide the second label.

## 2026-08-16T23:51:37+0600
- Added `public/images/mups/mups-hero-surgery.jpg` (landscape OT / ultrasound-guided procedure) and pointed the MUPS homepage hero at it.

## 2026-08-16T23:54:25+0600
- Correction: the 23:49 clinic `doctor_grid` one-card side-by-side layout (from 640px up) was omitted from this log when the hero photo line was added; that CSS still stands in `public/css/clinic-clireo.css`.
- MUPS tab icon is now `public/images/mups/favicon.svg` (navy rounded tile, nerve mark, green EKG) instead of the wide wordmark logo.

## 2026-08-16T23:56:06+0600
- Clinic About mission heading (`.about-head`) widened from 45rem to 62rem; MUPS `mission_statement` shortened to two sentences.

## 2026-08-17T00:01:19+0600
- Added `platform_settings` (singleton) plus Super Admin **Booking window** (`/admin/booking-window`) so one `patient_booking_horizon_days` value (default 60) caps online booking on every Front door; included in platform backup.

## 2026-08-17T00:04:00+0600
- Clinic homepage `.hero` is no longer a full 100vh under the nav; it fills `100dvh` minus `--clinic-nav-height` so the nav and hero share one viewport.

## 2026-08-17T06:47:18+0600
- MUPS homepage CMS copy rewritten for conversion (hero, about, treatments strip, promise cards, bottom CTA); facts still from the practice, slogan dropped.

## 2026-08-17T06:56:24+0600
- Clinic Find us (`location_hours`) stacks each sitting’s hours in a column instead of one dotted line beside HOURS.

## 2026-08-17T07:06:10+0600
- Clinic Voices of relief (`testimonials`) is a looping auto-scroller (`data-review-scroll`); MUPS homepage now has eight review cards.

## 2026-08-17T07:17:40+0600
- One-doctor clinic `doc-card` name panel (`.meta`) no longer has a white fill.

## 2026-08-17T07:18:54+0600
- Reverted the one-doctor clinic `doc-grid` side-by-side layout; the Founder card is stacked again (photo, then name).

## 2026-08-17T07:20:49+0600
- MUPS homepage `stat_band` sits under the hero; clinic `doctor_grid` `.docs-split` is heading | portrait from 1200px when there is one doctor; `stat_band` has an optional heading in Filament.

## 2026-08-17T11:32:16+0600
- Sitting form minutes-each hint now uses a real **Time per patient** label and plain English copy (tight sittings get a stronger warning).

## 2026-08-17T11:37:35+0600
- Slot-cap pace copy moved under the Slot cap input as short helper text; the separate **Time per patient** Placeholder is gone.

## 2026-08-17T11:40:47+0600
- Daily Roster Collect fee (Stations off) shows cash ৳ / online ৳ / online method when Paid how is Cash + online; `ChamberCashService::recordPatientIncome()` stores that split.

## 2026-08-17T11:43:41+0600
- Added `MupsDemoSeeder` (called from `MupsSeeder`) so the MUPS clinic admin has patients, today's live queue, visit notes/Rx, cashbook, labs, slot blocks, and sitting overrides.

## 2026-08-17T11:58:59+0600
- Added tenant `cash_categories` table, `CashCategoryService`, and admin **Operations → Cash categories** so owners can add/hide income and expense labels; Cashbook **Add income** / **Add expense** now pick from active categories.

## 2026-08-17T14:01:45+0600
- Staff can mark a person as treated here before ChamberQ (`patients.seen_before_software`): walk-in checkbox and Daily Roster / Live Queue row toggle (`PatientContinuityActions`); Consult Screen shows “paper file” instead of first visit until a real completed visit exists.

## 2026-08-17T15:30:27+0600
- Queue: `startSession()` nested savepoint for unique-index races; offline arrived/skip wrong-status is a conflict; replayed Call next while the room is occupied is a no-op (room-free spec).
- E: Public wizard and `POST /api/bookings` only accept publicly bookable sittings (`visit` / leftover `consult`).
- A: Added `KIND_COUNSELING`; counseling sitting per branch day; **Send to counseling** from a done procedure (transactional, free, no voucher); `sendVisitToIntervention()` now transactional.
- B: **Operations → Missed procedures** worklist (WhatsApp + Move; no auto-cancel).
- C: Voucher assignment holds the lock across write; unique `(tenant_id, booking_date, voucher_number)`.
- D: Voucher on the patient ticket; Daily Roster voucher column searchable.
- F: Combined chamber TV `/screen/chamber/{chamber}` (omit idle rooms; queued audio; contrast rules outrank `theme_color`).

## 2026-08-17T15:48:27+0600
- Correction: `clinic_nav_items()` was documented on 2026-08-16 but was never in `app/helpers.php` — added now (Home / Services / Doctors / extra WebPages / Health tips, path-prefixed). Daily Roster **Collect fee** mixed cash+online fields were described as live but not passed into `recordPatientIncome()`; wired to match Cashbook. Clinic heading word-split now HTML-escapes each word. `apiChamberToday` loads live sessions in one `whereIn`. `GET /bookings/{booking}` throttled 60/min. Unused `customPages` WebPage query dropped from clinic layout/homepage (nav helper already loads extras).

## 2026-08-17T15:55:12+0600
- MUPS is a two-branch clinic (Panchlaish + Uttara; Epic Dhanmondi removed). Super Admin modules all on (Website, Queue, Rx, Stations, Referrals, HR) plus Bangla homepage and live-average / chime+Bangla-voice queue branding. Each branch-day seeds Intervention / Visit / Counseling. Developer handoff HTML in `docs/ChamberQ-Developer-Handoff-MUPS.html`.

## 2026-08-17T18:41:08+0600
- Production-audit follow-up: chamber TV JSON dropped booking UUIDs (`announce_key` instead); ZIP extract rejects `..` paths; Move intervention clears the live pointer and respects staff cap; public store/availability use `PlatformSetting` horizon; counseling walk-ins blocked in `BookingService`; visit media writes check ownership; offline queue replay locks the session; seeders refuse production and do not reset passwords; follow-up reminders use a date-range + `visit_records_follow_up_index`.



## 2026-08-17
- Closed the gaps around the Stations pathway: counseling hand-off visibility now asserts a counseling sitting exists, a post-commit voucher failure can no longer fail a booking that was already taken, and the publicly-bookable rule moved into a `scopePubliclyBookable()` query scope so the availability and open-dates endpoints stop reporting on intervention sittings. Both Bangla scan lists widened to reach model/service labels and the combined chamber TV view.

## 2026-08-17T21:32:19+0600
- Solo demo seed now fills every staff screen the `solo` tenant actually has (queue statuses, prescriptions, cashbook, SMS, closed days, sitting override, waitlist, follow-up reminders, My medicines packs) plus extra homepage CMS blocks; re-run with `SoloDemoSeeder`.

## 2026-08-17T22:43:46+0600
- Daily Roster / Live Queue row button `Old patient (paper file)` renamed to **For follow up** (`PatientContinuityActions`); behaviour unchanged.

## 2026-08-18T14:01:13+0600
- Staff desk scope (`StaffDeskScope`, `chamber_user`, `users.assigned_doctor_id`) — branch lock and doctor-assistant stamps on staff/doctor logins; Staff & Roles grouped by job; roster/queue/cash/sittings/offline APIs filtered to allowed chambers and doctors.

## 2026-08-18T14:01:13+0600 (correction)
- The 2026-08-18T13:45:36 architecture_history line for tenant **`helper`** (ChamberQHelperAccess / bootstrap) was accidentally dropped when this entry was appended; that helper work remains in force from commit e4dc6f6.

## 2026-08-18T14:20:41+0600
- Desk jobs on Staff logins (`users.desk_jobs`, `users.desk_is_lead`, `StaffDeskJobs`) — money / queue / prep ticks plus lead-desk hiring; gates on roster, Cashbook, Live Queue, vitals, offline queue, and fee-setup resources.

## 2026-08-18T14:26:23+0600
- Production hardening pass: Staff & Roles branch sync via form getState; lead hire chamber validation; fee/referral admin-only setup; Stations till+referral one transaction; follow-up SMS row lock; day-of-week DB indexes; TRUSTED_PROXIES env; cancellation SMS custom body capped.

## 2026-08-18T14:49:03+0600
- Portal prescription password (`portal_phone_passwords`, `PortalPrescriptionLock`): after a completed visit, old pads on `/portal` need a per-clinic phone password; serials stay phone-only; SMS `/p/{token}` unchanged.

## 2026-08-18T14:53:25+0600
- Portal prescription password is opt-in: pads stay phone-open until the patient chooses a lock; doctor shared-history paths do not use `PortalPrescriptionLock`.

## 2026-08-18T17:36:15+0600
- Clinic hero photo is `--hero-photo` on `.hero-bg` (overlay on `::after`); `Tenant::cssThemeColor()` sanitises `--brand` to a hex so a bad `theme_color` cannot blank the MUPS (and other clinic) homepage hero.

## 2026-08-18T17:43:15+0600
- Clinic hero on multi-branch sites asks Which centre? first; homepage sittings are publicly bookable only; `POST /book` drops a sitting that is not at the chosen chamber.

## 2026-08-18T17:50:11+0600
- Clinic hero date control is a Select date list of sitting days (empty until chosen), not a native calendar that paints today as if selected.

## 2026-08-18T18:24:32+0600
- Patients store `year_of_birth`; booking / walk-in ask for birth year instead of a ticking age in years. `YearOfBirth` converts leftover `age` posts once; `displayAge()` is this calendar year minus the stored year (exact DOB still wins).

## 2026-08-18T18:41:39+0600
- Clinic hero booking card asks phone before name (doctor is its own field). Homepage and `/doctors` team grids (`.doc-grid--team`) are a 3-column 3-row window that scrolls; one doctor occupies one of three columns.

## 2026-08-19T13:52:07+0600
- Desk row compact (`DeskActionLayout`, `RosterRecordActions`, `QueueRecordActions`, shared `CollectFeeAction` / `OutdoorVitalsAction`): max two primaries plus More; Live Queue is the during-sitting counter (Collect fee after checkup); Daily Roster is open/close leftover; `tenants.collect_fee_at_checkin` Branding Desk toggle (default off).

## 2026-08-19T14:39:32+0600
- Maestro list ৳25,000 / ৳3,000; module units ৳5,000 / ৳18,000 / ৳4,500. Medical representatives, paying-price override, per-deal % overrides. Commission clock: join 50/20 (direct 20%), year 1 monthly 0%, year 1 prepaid 15/5 (direct 20%), year 2+ 5/5 (direct 10%). Prepaid year no longer halves setup. `DealCommissionRates` + `commissions.payee_key`.

## 2026-08-19T19:22:45+0600
- Clinic `POST /` (and `POST /{tenant}/`) is the same hero prefill as `POST /book`, so a homepage form submit no longer 405s; marketing `POST /` stays refused.

## 2026-08-19T19:33:18+0600
- `ForceRequestRootUrl` on the `web` group; patient `sw.js` (clinic-shell-v9) skips `/admin` and `/livewire/`; hero `prefill()` ignores Livewire requests.

## 2026-08-19T19:35:46+0600
- Stations care path: `CarePath` + `bookings.care_path` / `care_branch` / `care_origin_id`; room kinds `msk` and `report`; MUPS seed Visit/MSK/Intervention/Report/Counseling; All-rooms TV cap 6.

## 2026-08-19T20:05:04+0600
- Filament `drop-patient-service-workers` HEAD hook; `sw.js` clinic-shell-v10 unregisters on `/admin` or `/livewire/` so leftover path-tenant PWAs cannot own the desk.

## 2026-08-19T21:41:39+0600
- Live Queue / Daily Roster walk-in pass `allowEndedToday`; `BookingUnavailableException` no longer `back()`s on Livewire (422 JSON + desk notification).

## 2026-08-19T21:43:57+0600
- Door pay + missed-visit refund (`PatientFeeRefundService`, `patient_refund` cashbook category, unique booking cash-row dropped); per-doctor `collect_fee_at_checkin`; MSK is a ৳2,200 priced scan with desk-only walk-in and optional GP cut via `referral_msk_commission_taka`.

## 2026-08-19T21:45:32+0600
- Tenant admin mobile sidebar: wrapping brand name, scrollable nav (`min-height: 0`), safe-area padding, `viewport-fit=cover`.

## 2026-08-19T22:05:02+0600
- Phone admin drawer z-index 50 / overlay 45 so the sticky content header (z-index 40) cannot paint Session Actions over the menu; page hamburger is `position: fixed`.

## 2026-08-19T22:08:01+0600
- Phone content header stacks: page title on row one, header actions (Session Actions / New Walk-In) on a full-width second row.

## 2026-08-19T22:12:45+0600
- Staff pocket-buzz card (`.staff-buzz-card`) uses theme surfaces in `tenantAdmin/theme.css` so dark mode is not white-on-white.

## 2026-08-19T22:15:21+0600
- Clinic/doctor `practice_rules` JSON (`PracticeRules`, Branding + Doctors forms): configurable follow-up window and report/counseling prices; `CarePath` and till/vouchers read it instead of a hardcoded 3-month / always-free rule.

## 2026-08-19T22:26:14+0600
- Outside-GP cuts live on `practice_rules` (Branding); `ReferralCommissionService` no longer hardcodes ৳200 / ৳1,000 for every client.

## 2026-08-19T22:32:01+0600
- Tenant admin **Book serial** (`Pages/BookSerial` + `StaffBookingForm`): staff book a public sitting on a chosen date without using the patient website.

## 2026-08-19T23:23:45+0600
- Daily Roster lists a chosen date (`rosterDate`); walk-in / Mark Late / Live Queue remain today-only.

## 2026-08-19T23:30:11+0600
- Book serial shows a confirmation modal (`book-serial-confirmed`) with serial, WhatsApp, and ticket after a successful book.

## 2026-08-19T23:45:02+0600
- Public SEO: `PublicSeo` / `PublicSitemap` / `SeoController`, `partials/seo.blade.php`, Laravel `robots.txt` + `sitemap.xml` (removed static `public/robots.txt`).

## 2026-08-19T23:48:20+0600
- Book serial: optional Different WhatsApp (`bookings.whatsapp_phone`), same as the public wizard.

## 2026-08-19T23:57:28+0600
- Book serial visit type (Usual / Follow-up / Intervention / Lab with MSK and lab-test subtypes); paper-file checkbox removed from this page.

## 2026-08-20T00:03:55+0600
- Per-chamber patient booking window (`tenants.patient_booking_horizon_days`); MUPS set to 3 days; platform default remains 60.

## 2026-08-20T00:11:53+0600
- Book serial / Book intervention pick fee-catalogue procedure type (PRP, epidural, …); `bookings.fee_catalog_item_id`.

## 2026-08-20T00:20:55+0600
- Booking window is only platform_settings plus optional per-tenant days — no MUPS/60 special case in seed or copy.

## 2026-08-20T00:39:34+0600
- Daily Roster and Live Queue **New Walk-In** share `StaffBookingForm` visit types (today, overflow, ended-today); Follow-up replaces the walk-in paper-file checkbox. Single-job staff login home is Live Queue / Cashbook / Daily Roster.



