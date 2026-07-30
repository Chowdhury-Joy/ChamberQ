# Site Map
Last Updated: 2026-07-31T04:15:00+06:00

## Full Site Map

### Central (marketing + Super Admin)
Hosts: values in `CENTRAL_DOMAINS` (e.g. `localhost`).

| Route | Purpose | Access |
|-------|---------|--------|
| `/` | Sales landing for Doctor Gemini (Solo/Clinic plans, WhatsApp CTAs) | public |
| `/admin` | Super Admin Filament login | public login |
| `/admin/*` | Super Admin: Tenants (create/edit slug, optional custom domains, plan_tier, feature_flags, SMS top-up, billing_status) | super_admin only |
| `/up` | Laravel health check | public |

### Platform tenant (central path tenancy)
Same central host; tenant identified by URL slug (tenant `id`), e.g. `drkarim`.

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug}/` | Branded website home | public |
| `/{slug}/book` | Booking wizard | public |
| `/{slug}/bookings/{booking}` | Patient ticket | public (UUID) |
| `/{slug}/portal` | Phone lookup | public (throttled) |
| `/{slug}/screen/{session}/{date}` | Outdoor display | public |
| `/{slug}/admin` | Tenant staff Filament panel | staff login |
| `/{slug}/manifest.webmanifest`, `/{slug}/sw.js`, … | PWA | public |

Example: `https://doctorgemini.com/drkarim/book`

### Tenant public (custom domain — optional)
When a doctor connects their own domain (e.g. `drkarim.com`), routes live at the **root** (no `/{slug}` prefix).

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug?}` | Branded website pages from WebPage builder (home = empty slug) | public |
| `/book` | Online serial booking wizard | public |
| `/bookings/{booking}` | Patient ticket (UUID) | public |
| `/portal` | Phone lookup | public (throttled) |
| `/screen/{session}/{date}` | Outdoor waiting-room display | public (throttled) |
| `/lang/{locale}` | Switch session locale `en` / `bn` | public |
| `/manifest.webmanifest`, `/sw.js`, `/pwa-icon-{192\|512}.svg` | PWA bits | public |
| `/admin` | Tenant staff Filament panel | staff login |

### Tenant public APIs
Available under both platform path (`/{slug}/api/…`) and custom domain (`/api/…`).

| Route | Purpose | Access |
|-------|---------|--------|
| `GET /api/bookings/availability` | Session/lab availability for wizard | public (throttled) |
| `POST /api/bookings` | Create booking | public (throttled; blocked if billing closed) |
| `GET /api/queue/{booking}` | Ticket queue poll by booking UUID | public (throttled) |
| `GET /api/screen/{session}/{date}` | Screen poll payload | public (throttled) |

## Customer Journeys

### New visitor → interested lead (central sales)
1. Land on `/` — understand Solo vs Clinic and pricing. Goal: trust + clarity.
2. Tap WhatsApp CTA. Goal: start sales chat.
3. Human sales/onboarding creates tenant in Super Admin with URL slug. Goal: go-live at `/{slug}`.

### Patient → book serial → ticket
1. Open `/{slug}/` or custom domain home — see doctor brand + Book CTA.
2. Book flow — pick session/date, enter name/phone.
3. Submit → ticket at `…/bookings/{uuid}`. Goal: proof of serial; share via WhatsApp/copy.
4. Optional: PWA install scoped to tenant path or custom domain.

### Patient → check status later
1. Open `/portal` or ticket link.
2. Portal: enter BD phone → see matching bookings.

### Patient → waiting room
1. Watch outdoor screen URL for today's session.
2. Hear call chime when serial is called.

## Admin/Staff Journeys

### New tenant → go live (Super Admin)
- **Trigger:** Sales closes a doctor/clinic.
- **Steps:** Create Tenant with URL **slug** (e.g. `drkarim`) → optional custom **domain** → set `plan_tier`, SMS, `billing_status` → hand off admin login.
- **URLs:** Platform `/{slug}/…`; after custom domain DNS, also `drkarim.com/…` at root.
- **Success:** `/{slug}/book` works; admin at `/{slug}/admin` (or `/admin` on custom domain).

### Open clinic day → run queue
- **Trigger:** Session day starts.
- **Steps:** Live Queue Control → Start → Call → Patient arrived → Complete.
- **Success:** Outdoor screen matches control panel.

### Content update (staff)
- **Trigger:** Doctor wants copy/photo change.
- **Steps:** Staff edits Web Page blocks in tenant admin.

### Ops review (admin/doctor)
- **Trigger:** End of day/week.
- **Steps:** Operational Reports → day/week/month KPIs.
