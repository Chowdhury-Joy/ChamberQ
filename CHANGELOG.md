# Changelog

All notable changes to Doctor Gemini are documented here.

Format roughly follows [Keep a Changelog](https://keepachangelog.com/).
Solo-doctor v1 work lives on branch `Solo-Doc-V1`.

## [Unreleased]

### Planned — Phase 2 (screen / ticket polish)
- Configurable call-audio track for the waiting-room screen
- End-session cleanup for remaining waiting patients
- Empty states on book/queue flows

### Planned — Phase 3 (pre-patient-data hardening)
- Escape `innerHTML` in booking wizard and screen pause reason
- URL scheme allowlist on page-builder links
- Feature-flag boolean handling; basic form validation (`end > start`, `slot_cap ≥ 1`)

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
