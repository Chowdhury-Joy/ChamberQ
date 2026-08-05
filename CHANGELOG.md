# Changelog

All notable changes to Doctor Gemini are documented here.

Format roughly follows [Keep a Changelog](https://keepachangelog.com/).
Solo-doctor v1 work lives on branch `Solo-Doc-V1`.

## [Unreleased]

### Added
- Waiting-room call announcements: Branding → Live Queue Settings — chime only, voice only (“Calling number N”), or chime + voice; optional Bangla phrase via browser speech

### Changed
- Patient marketing site: brand-first full-bleed hero with Book CTA; Portal quiet in nav; mobile menu; default theme blue (`#2563eb`) and English locale
- Homepage EN/BN switch only when Super Admin enables `bangla_homepage` (paid add-on); Book/Ticket/Portal always offer EN/BN for system strings
- Booking Phase A: session cards show seats left / Full / Closed for the next matching day; identity step review strip; local (not UTC) dates; BD phone check before submit; “Booking…” while saving; capacity race returns a clear code + message; phones stored normalized as `01…`
- Booking Phase B: ticket handoff for reception + WhatsApp/copy link; “Now serving” i18n fixed; people-ahead ignores skipped serials; WhatsApp/copy include chamber Google Maps link when lat/lng or address exists
- Booking Phase C: book wizard JS strings follow EN/BN; lab Continue requires ≥1 test; tighter mobile padding; `?doctor=` / `?test=` deep links skip extra steps and pre-check tests
- Booking Phase D: `per_day` slot_cap_mode aliases to `per_doctor_chamber`; ending a live session completes `in_chamber` patients instead of cancelling them; happy-path booking tests

## [Solo-Doc-V1 Phase 3] — 2026-07-27

### Fixed
- Booking wizard session/lab cards now use `textContent` / `createElement` (no `innerHTML` interpolation of doctor/chamber/session names)
- Waiting-room screen pause reason already uses `textContent` (confirmed safe)
- Page-builder CTA and media links allowlisted to `http` / `https` / `mailto` / `tel` plus relative `/` and `#` paths (`SafeUrl`); scrubbed on `WebPage` save and at render
- Feature-flag string `"false"` / `"0"` now correctly disables overrides (Filament KeyValue no longer forces features on via `(bool)"false"`)
- Schedule sessions and lab collection slots require `end_time > start_time` and `slot_cap ≥ 1`

## [Solo-Doc-V1 Phase 2] — 2026-07-27

### Added
- Waiting-room call audio presets (`chime`, `soft-bell`, `alert`) plus custom upload in Branding → Live Queue Settings
- Visible **Tap to enable sound** overlay and mute toggle on the outdoor screen (browser autoplay unlock)
- Default WAV chimes under `public/audio/`
- Book page empty state when the clinic has no bookable doctors/sessions (or lab slots)
- Live Queue empty state when no sessions are scheduled for today (ops users get a link to schedules)

### Fixed
- `endSession` now cancels remaining non-terminal bookings (`waiting` / `called` / `skipped` / `in_chamber`) with reason “Session ended”, clears the current call, and marks the live session completed (modal copy already promised this)
- Screen shows a clear “session ended” state after finish

## [Solo-Doc-V1] — 2026-07-27

### Added
- Solo staff roles: `admin`, `doctor`, `staff` (replaces `tenant_admin` / `web_developer` / `content_editor`)
- Role capability helpers on `User` (`canManageOps`, `canManageContent`, `canManageQueue`, etc.)
- Filament access matrix:
  - **Admin** — full access (ops, queue, page builder structure, branding, users)
  - **Doctor** — ops + queue only
  - **Staff** — content text/image edit + queue only
- `ChamberPolicy` with solo single-chamber create/delete limits
- Migration `2026_07_27_130000_remap_tenant_staff_roles` to rename legacy roles in existing DBs
- Seed accounts for solo: `admin@solo.com`, `doctor@solo.com`, `staff@solo.com` (password: `password`)
- This changelog

### Changed
- Live Queue session picker lists **today’s sessions only**
- Lab resources require ops role **and** `lab_tests` feature
- Doctors/Chambers sidebar still hidden on solo tier; create capped at one each

### Removed
- Entire payment stack (bKash, Nagad, SSLCommerz gateways, webhook controller, `PaymentTransaction`, payment env/config)
- Booking columns `payment_status`, `payment_reference`, `refund_eligible`
- Payment webhook route and CSRF exemption

### Fixed (Phase 0 — demo readiness)
- Booking form errors now display (inline `display:none` no longer hides them)
- Booking dates must be today through +60 days (server + date picker)
- Doctor deep-link from website (`?doctor=` / legacy `?doctor_id=`)
- “Add Sample Patients” local-only; no longer rewrites schedule `day_of_week`
- Patient portal uses exact phone match (with `01` / `88` / `+88` variants) and rate limiting
- Submit button disables during booking request to reduce double-booking

### Product decisions (v1)
- No payment gateways — patients book a serial and pay at the clinic
- No patient login accounts — status via ticket UUID link or portal phone lookup
- Waiting-room screen shows full patient name
- Page builder kept (admin owns structure; staff edits existing text/images)

### Commits
- `118d5ab` — Remove payment gateway stack for pay-at-clinic v1
- `3885366` — Harden booking and portal flows for solo demo readiness
- *(this commit)* — Solo admin/doctor/staff roles and access matrix
