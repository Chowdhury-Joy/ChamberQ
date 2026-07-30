# Architecture Overview
Last Updated: 2026-07-31T02:15:00+06:00

## Overview
Doctor Gemini is a multi-tenant SaaS for Bangladesh solo doctors and clinics. Each tenant gets a branded patient website, online serial booking, a live waiting-room queue (outdoor screen + staff control), a patient ticket/portal, and a Filament admin panel. Patients book a serial and pay at the chamber — there is no payment gateway. Online pre-payment is later-stage only: do not suggest or build it unless the owner explicitly asks. Sales CTAs on the central marketing site use WhatsApp (`wa.me`). SMS booking confirmations use a prepaid credit wallet topped up by Super Admin.

## Getting Started
Prerequisites: PHP 8.2+, Composer, Node/npm (for Vite assets), SQLite (local) or MySQL/Postgres (production).

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # if using sqlite
php artisan migrate --seed
php artisan serve
```

Optional: `composer run dev` (server + queue + Vite + logs).

Key environment variables (names only): `APP_KEY`, `APP_URL`, `APP_TIMEZONE`, `CENTRAL_DOMAINS`, `DB_*`, `MAIL_*`, `MARKETING_WHATSAPP`, `MARKETING_PHONE`, `MARKETING_SOLO_*`, `MARKETING_CLINIC_*`, `MARKETING_SMS_CREDIT_PRICE`, `SMS_DRIVER`, `SMS_ENABLED`, `SMS_HTTP_URL`, `SMS_HTTP_API_KEY`, `SMS_HTTP_SENDER`.

Local domains:
- Central marketing + Super Admin: `http://localhost/` and `http://localhost/admin`
- Seeded tenant example: `http://solo.localhost/` and `http://solo.localhost/admin` (map in `/etc/hosts` if needed)

Tests: `php artisan test` (also CI via `.github/workflows/tests.yml`).

## Tech Stack
- PHP ^8.2, Laravel ^12, Filament ^4
- stancl/tenancy ^3.10 (domain-based multi-tenancy)
- mews/purifier ^3.4 (HTML sanitization for page builder)
- Livewire (via Filament)
- Blade views for patient site, booking wizard, ticket, portal, waiting-room screen
- Vite ^7 + Tailwind CSS ^4 (front-end build)
- PHPUnit ^11 for feature tests
- SQLite locally; MySQL/Postgres intended for production
- SMS: `log` driver (default) or `http` JSON POST gateway; prepaid wallet on `tenants.sms_balance`

## Folder Structure
- `app/Models` — Tenant, Domain, User, Doctor, Chamber, ScheduleSession, Booking, LiveSession, LabTest, LabCollectionSlot, BookingLabTest, SlotBlock, WebPage, SmsMessage
- `app/Services` — BookingService, LiveSessionService, OperationalReportService, SlotBlockService, SmsService (+ SMS drivers)
- `app/Http/Controllers` — BookingController, WebPageController, ScreenController, QueueStatusController, PWAController
- `app/Http/Middleware` — Localization, EnsureTenantAcceptsBookings, and tenancy middleware from stancl
- `app/Filament/SuperAdmin` — central Super Admin resources/widgets (Tenants)
- `app/Filament/TenantAdmin` — tenant panel pages (Live Queue, Daily Roster, Ops Reports, Branding) and resources (Doctors, Chambers, Schedules, Labs, WebPages, Users, SlotBlocks)
- `app/Policies` — authorization for chambers, doctors, labs, etc., gated by plan features and roles
- `app/Providers/Filament` — SuperAdminPanelProvider, TenantAdminPanelProvider (both path `/admin`, domain-scoped)
- `config/` — `tenancy.php`, `marketing.php`, `sms.php`, app/mail/etc.
- `database/migrations`, `database/seeders` — schema + demo solo tenant
- `resources/views` — `marketing/`, `tenant/solo/`, shared booking/screen blades, Filament custom pages
- `routes/web.php` — central domain routes (marketing home)
- `routes/tenant.php` — tenant public + API routes
- `lang/` — EN/BN strings
- `public/` — entrypoint, audio chimes, built assets
- `tests/Feature` — booking, queue, ops reports, access control
- Project memory: `decisions.md`, `bug_history.md`, `architecture.md`, `architecture_history.md`, `sitemap.md`

## Key Components
- **Tenant + plan features** (`Tenant::hasFeature`, `Tenant::maxChambers`) — `plan_tier` `solo` | `clinic` with defaults; per-tenant `feature_flags` JSON can override. Solo defaults: `multiple_chambers` on (cap 5 via `SOLO_MAX_CHAMBERS`), no `lab_tests`, no `multiple_doctors`. Clinic defaults: all three on, unlimited chambers. `bangla_homepage` is a paid add-on flag.
- **BookingService** — creates serials, capacity/slot rules, lab test attachment when feature enabled, phone normalization (`01…`), billing gate via `acceptsBookings()`.
- **LiveSessionService** — start/end session, call next, mark arrived (`in_chamber`), skip, complete; drives outdoor screen + Live Queue Control.
- **OperationalReportService** — day/week/month booking aggregates for staff.
- **SmsService** — confirm booking SMS; debit 1 credit on success; skip send if wallet empty (booking still succeeds).
- **Web page builder** — Filament WebPages resource; solo template sections under `resources/views/tenant/solo/`; SafeUrl allowlist for links.
- **Roles** — Super Admin (central); tenant `admin` / `doctor` / `staff` with capability helpers (`canManageOps`, `canManageContent`, `canManageQueue`, etc.).
- **Marketing site** — central `config/marketing.php` plans/pricing/WhatsApp CTAs; Blade home at `resources/views/marketing/home.blade.php`.

## Data Flow
1. **Sales:** Visitor hits central `/` → WhatsApp CTA → Super Admin creates tenant + domain + tops up SMS.
2. **Patient book:** Tenant site → `/book` wizard → `GET /api/bookings/availability` → `POST /api/bookings` → BookingService creates booking → optional SMS debit → redirect to `/bookings/{uuid}` ticket.
3. **Clinic day:** Staff open Live Queue Control → start session for today’s ScheduleSession → call patients → Patient arrived (`in_chamber`) → complete. Outdoor TV polls `/api/screen/{session}/{date}`. Ticket page polls `/api/queue/{booking}`.
4. **Lookup:** Patient opens `/portal`, enters phone → exact-match booking list (throttled).
5. **Content:** Admin structures WebPages; staff edits text/images; public `/{slug?}` renders tenant site.
6. **Labs (clinic tier / flag):** Patient can add lab tests + collection slot during booking; ops manage LabTests + LabCollectionSlots in admin.

## Integrations
- **stancl/tenancy** — domain identification; central vs tenant route split
- **Filament 4** — Super Admin + Tenant Admin panels
- **WhatsApp** — outbound only via free `wa.me` links (no Business API)
- **SMS gateway** — optional HTTP driver; default `log` for local/tests
- **Mail** — Laravel mailers for Filament password reset (configure `MAIL_*` in production)
- **No payment gateways** — intentionally removed for pay-at-chamber v1; online pre-payment is later-stage only and must not be suggested unless the owner asks
