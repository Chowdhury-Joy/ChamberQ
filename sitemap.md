# Site Map
Last Updated: 2026-07-31T02:15:00+06:00

## Full Site Map

### Central (marketing + Super Admin)
Hosts: values in `CENTRAL_DOMAINS` (e.g. `localhost`).

| Route | Purpose | Access |
|-------|---------|--------|
| `/` | Sales landing for Doctor Gemini (Solo/Clinic plans, WhatsApp CTAs) | public |
| `/admin` | Super Admin Filament login | public login |
| `/admin/*` | Super Admin: Tenants (create/edit domains, plan_tier, feature_flags, SMS top-up, billing_status) | super_admin only |
| `/up` | Laravel health check | public |

### Tenant public (patient-facing)
Hosts: per-tenant domains (e.g. `solo.localhost`).

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug?}` | Branded website pages from WebPage builder (home = empty slug) | public |
| `/book` | Online serial booking wizard (doctor/chamber/session, optional labs if feature on) | public |
| `/bookings/{booking}` | Patient ticket (UUID); queue status + WhatsApp/copy share | public (secret UUID) |
| `/portal` | Phone lookup for past/upcoming bookings | public (throttled) |
| `/screen/{session}/{date}` | Outdoor waiting-room display | public (throttled) |
| `/lang/{locale}` | Switch session locale `en` / `bn` | public |
| `/manifest.webmanifest`, `/sw.js`, `/pwa-icon-{192\|512}.svg` | PWA bits | public |

### Tenant public APIs

| Route | Purpose | Access |
|-------|---------|--------|
| `GET /api/bookings/availability` | Session/lab availability for wizard | public (throttled) |
| `POST /api/bookings` | Create booking | public (throttled; blocked if billing closed) |
| `GET /api/queue/{booking}` | Ticket queue poll by booking UUID | public (throttled) |
| `GET /api/screen/{session}/{date}` | Screen poll payload | public (throttled) |

### Tenant Admin (staff)
Same host as tenant site; path `/admin`.

| Area | Purpose | Access |
|------|---------|--------|
| `/admin` login | Tenant staff sign-in | public login |
| Live Queue Control | Start/end session, call, arrived, skip, complete | admin, doctor, staff (`canManageQueue`) |
| Daily Roster | Day list of bookings; call/complete actions | ops + queue roles |
| Operational Reports | Day/week/month booking KPIs | ops roles |
| Branding Settings | Theme, logo, live-queue audio, etc. | admin |
| Web Pages | Page builder structure (admin) / content edit (staff) | admin structure; staff content |
| Schedule Sessions | Recurring doctor sessions | admin, doctor |
| Slot Blocks | Block dates/slots | admin, doctor |
| Doctors | Manage doctors | clinic / `multiple_doctors` (solo: capped, sidebar often hidden) |
| Chambers | Manage chambers | Solo: up to 5 (`multiple_chambers`, cap via policy); Clinic: unlimited |
| Lab Tests | Lab catalogue | `lab_tests` feature + ops |
| Lab Collection Slots | Lab collection windows | `lab_tests` feature + ops |
| Users | Tenant staff accounts | admin |

## Customer Journeys

### New visitor → interested lead (central sales)
1. Land on `/` — understand Solo vs Clinic and pricing. Goal: trust + clarity.
2. Tap WhatsApp CTA (“I’m a solo doctor interested…”). Goal: start sales chat.
3. Human sales/onboarding creates tenant in Super Admin. Goal: go-live.

### Patient → book serial → ticket
1. Open tenant homepage — see doctor brand + Book CTA.
2. `/book` — pick doctor/chamber (if multi), session/date, enter name/phone; optionally lab tests if enabled.
3. Submit → ticket at `/bookings/{uuid}`. Goal: proof of serial; share via WhatsApp/copy.
4. Optional: enable PWA / keep ticket open for live “people ahead / now serving”.

### Patient → check status later
1. Open `/portal` or ticket link.
2. Portal: enter BD phone → see matching bookings. Goal: find serial without login.
3. Ticket: poll queue API. Goal: know when to go in.

### Patient → waiting room
1. Arrive at chamber; watch outdoor `/screen/{session}/{date}`.
2. Hear call chime when serial is called. Goal: know when to enter.

## Admin/Staff Journeys

### New tenant → go live (Super Admin)
- **Trigger:** Sales closes a doctor/clinic.
- **Steps:** Create Tenant → set domain, `plan_tier`, branding defaults → optional `feature_flags` → top up SMS credits → set `billing_status` active/trial → seed or hand off admin login.
- **Data/systems:** `tenants`, `domains`, SMS balance, marketing config for pricing only.
- **Success:** Tenant domain loads site; `/book` works; admin can log into `/admin`.

### Open clinic day → run queue
- **Trigger:** Session day starts.
- **Steps:** Admin/Doctor/Staff open Live Queue Control → select today’s session → Start → Call next → Patient arrived (`in_chamber`) → Complete (or Skip). End session when done (completes in-chamber; cancels remaining waiting/called/skipped).
- **Data/systems:** LiveSession, Bookings, Screen API, call audio settings.
- **Success:** Outdoor screen matches control panel; no patient labeled “serving” until `in_chamber`.

### Content update (staff)
- **Trigger:** Doctor wants copy/photo change.
- **Steps:** Staff edits Web Page blocks (text/images); Admin changes structure/blocks if needed.
- **Data/systems:** WebPages, media storage, SafeUrl scrubbing.
- **Success:** Public homepage reflects changes; links stay allowlisted.

### Ops review (admin/doctor)
- **Trigger:** End of day/week.
- **Steps:** Open Operational Reports → pick day/week/month → read KPIs (total, completed, still in queue, needs attention).
- **Data/systems:** OperationalReportService over bookings.
- **Success:** Counts match roster reality; empty state if zero bookings.
