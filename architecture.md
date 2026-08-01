# Architecture Overview
Last Updated: 2026-08-01T02:52:00+06:00

## Overview
Doctor Gemini is a multi-tenant SaaS for Bangladesh solo doctors and clinics. Each tenant gets a branded patient website, online serial booking, a live waiting-room queue (outdoor screen + staff control), a patient ticket/portal, and a Filament admin panel. Patients book a serial and pay at the chamber — there is no payment gateway. Online pre-payment is later-stage only: do not suggest or build it unless the owner explicitly asks. Sales CTAs on the central marketing site use WhatsApp (`wa.me`). SMS booking confirmations use a prepaid credit wallet topped up by Super Admin. Marketer partners earn commissions on setup and monthly subscription fees (manual bKash billing); Super Admin confirms doctor payments and marketer payouts.

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

Local URLs:
- Central marketing + Super Admin: `http://localhost/` and `http://localhost/admin`
- Marketer partner panel: `http://localhost/partner`
- Platform tenant (path tenancy): `http://localhost/solo/` (book at `/solo/book`, admin at `/solo/admin`)
- Custom domain tenant (optional): `http://solo.localhost/` and `http://solo.localhost/admin` (dev; production = doctor's own domain at root paths)

Tests: `php artisan test` (also CI via `.github/workflows/tests.yml`).

Scheduled tasks: `commissions:generate-monthly` runs on the 7th of each month (pending monthly commission rows for referred active tenants).

## Tech Stack
- PHP ^8.2, Laravel ^12, Filament ^4
- stancl/tenancy ^3.10 — hybrid tenancy: **path** on central domain (`/{tenant}/…`), **domain** for optional custom domains (root paths)
- mews/purifier ^3.4 (HTML sanitization for page builder)
- Livewire (via Filament)
- Blade views for patient site, booking wizard, ticket, portal, waiting-room screen
- Vite ^7 + Tailwind CSS ^4 (front-end build)
- PHPUnit ^11 for feature tests
- SQLite locally; MySQL/Postgres intended for production
- SMS: `log` driver (default) or `http` JSON POST gateway; prepaid wallet on `tenants.sms_balance`

## Folder Structure
- `app/Models` — Tenant, Domain, User, Doctor, Chamber, ScheduleSession, Booking, LiveSession, LabTest, LabCollectionSlot, BookingLabTest, SlotBlock, WebPage, SmsMessage, Marketer, DiscountCode, BillingPayment, Commission
- `app/Models/Relations` — custom Eloquent relations (e.g. `HasManyByScheduleAndDate` for LiveSession→Booking)
- `app/Services` — BookingService, LiveSessionService, OperationalReportService, SlotBlockService, SmsService, PlanPricingService, DiscountCalculator, CommissionService (+ SMS drivers)
- `app/Console/Commands/GenerateMonthlyCommissions.php` — `commissions:generate-monthly`
- `app/Http/Controllers` — BookingController, WebPageController, ScreenController, QueueStatusController, PWAController
- `app/Support/TenancyUrl.php` + `app/helpers.php` — `tenant_web_url()` / `tenant_web_route()` for path-aware links (not stancl's `tenant_route()`)
- `app/Support/CardGrid.php` — shared card-grid column rule (2 cols for 2/4 cards, otherwise 3)
- `app/Filament/Concerns/UsesCardGridColumns.php` — trait applying the card-grid rule to Filament stat widgets
- `app/Http/Middleware` — Localization, EnsureTenantAcceptsBookings, SetPathTenantUrlDefaults, CaptureReferralParams, stancl tenancy middleware
- `app/Filament/SuperAdmin` — central Super Admin resources/widgets (Tenants, Marketers, DiscountCodes, Commissions, finance widgets)
- `app/Filament/Marketer` — partner panel at `/partner` (referral link, stats, referred tenants, commission history)
- `app/Filament/TenantAdmin` — tenant panel (custom domains at `/admin`)
- `app/Providers/Filament/TenantAdminPathPanelProvider.php` — tenant panel on central domain at `/{tenant}/admin`
- `app/Providers/Filament/MarketerPanelProvider.php` — marketer panel on central domain at `/partner`
- `app/Policies` — authorization for chambers, doctors, labs, etc., gated by plan features and roles
- `app/Providers/Filament` — SuperAdminPanelProvider, MarketerPanelProvider, TenantAdminPanelProvider (both path `/admin`, domain-scoped)
- `config/` — `tenancy.php`, `marketing.php`, `sms.php`, app/mail/etc.
- `database/migrations`, `database/seeders` — schema + demo solo tenant
- `resources/views` — `marketing/`, `tenant/solo/`, shared booking/screen blades, Filament custom pages/widgets
- `resources/views/components` — shared Blade components (`<x-card-grid>`)
- `routes/web.php` — central domain routes (marketing home + referral capture middleware)
- `routes/tenant.php` — tenant public + API routes
- `routes/console.php` — scheduled `commissions:generate-monthly`
- `lang/` — EN/BN strings
- `public/` — entrypoint, audio chimes, built assets, `css/card-grid.css` (imported by `resources/css/app.css`; also `<link>`ed by marketing home and the Operational Reports panel page, which has no Vite theme)
- `tests/Feature` — booking, queue, ops reports, access control, marketer commissions, waiting-time ETA
- Project memory: `decisions.md`, `bug_history.md`, `architecture.md`, `architecture_history.md`, `sitemap.md`

## Key Components
- **Tenant + plan features** (`Tenant::hasFeature`, `Tenant::maxChambers`) — `plan_tier` `solo` | `clinic` with defaults; per-tenant `feature_flags` JSON can override. Solo defaults: `multiple_chambers` on (cap 5 via `SOLO_MAX_CHAMBERS`), no `lab_tests`, no `multiple_doctors`. Clinic defaults: all three on, unlimited chambers. `bangla_homepage` is a paid add-on flag.
- **Marketer commissions** — `Marketer` profile linked to central `User` (`role=marketer`). Referral via `?ref={code}`; discount codes via `?code=`. `CommissionService` snapshots list/due prices, creates pending setup/monthly commissions, confirms doctor payments → `owed`, marks marketer payouts → `paid`. Not commissionable: SMS packs.
- **BookingService** — single write path for every booking (online wizard, Daily Roster walk-in, Live Queue walk-in). Creates serials under a row lock, enforces capacity/slot rules, attaches lab tests when the feature is enabled, normalises phones to `01…` (so `+88…` and `01…` are one patient), and rejects a second live booking for the same phone on the same bookable + date (`duplicateBooking`). Billing gate via `acceptsBookings()`.
- **LiveSessionService** — start/end session, call next, mark arrived (`in_chamber`), skip, complete; drives outdoor screen + Live Queue Control. Patient ticket ETA via `estimatedTimeForBooking()` using tenant `eta_model`: `schedule_guess` (default), `live_average`, or `live_steady` (falls back to schedule guess until completed consults exist for live modes). Conservative display knobs on the tenant: `estimated_time_buffer_minutes`, `first_n_patients`, `first_n_arrival_offset_minutes` (Branding Settings) pad the shown “come around” time so early-session patients are not told to arrive at the raw schedule slot. `LiveSession::bookings()` uses `HasManyByScheduleAndDate` so eager load matches `session_date` + `booking_date`. Daily Roster writes through `bringBookingToChamber()` / `completeBooking()` rather than updating `Booking` directly, so roster actions keep `called_at` / `in_chamber_at` / `completed_at` and the live session's `current_booking_id` in sync with the outdoor screen and patient ticket.
- **Outdoor screen** (`resources/views/tenant/screen.blade.php`) — shows chamber, doctor, and `ScheduleSession::screenLabel()` (session name + time window) so per-session serials are not confused on one TV.
- **OperationalReportService** — day/week/month booking aggregates for staff.
- **SmsService** — confirm booking SMS after the booking transaction commits (booking never rolls back on SMS failure). Debits 1 prepaid credit **before** calling the gateway; refunds the credit if the gateway throws; skips send when the wallet is empty (`SmsMessage` row records every outcome). Path-tenant ticket links built from `config('app.url')`; custom-domain tenants use their Domain host. Custom call-audio uploads stored under `public` disk at `call-audio/{tenant_id}/`.
- **Web page builder** — Filament WebPages resource; solo template sections under `resources/views/tenant/solo/`; SafeUrl allowlist for links.
- **Roles** — Super Admin (central `/admin`); Marketer (central `/partner`); tenant `admin` / `doctor` / `staff` with capability helpers (`canManageOps`, `canManageContent`, `canManageQueue`, etc.).
- **Marketing site** — central `config/marketing.php` plans/pricing/WhatsApp CTAs; Blade home at `resources/views/marketing/home.blade.php`; session capture for referral/discount params.
- **Card grid standard** — one column rule for every card collection (marketing, tenant sections, admin stat widgets): mobile 1, tablet 2, desktop 2 when the count is 2 or 4 and otherwise 3. Blade uses `<x-card-grid :count="…">`; Filament stat widgets use the `UsesCardGridColumns` trait; both resolve through `App\Support\CardGrid`.

## Data Flow
1. **Sales:** Visitor hits central `/` (optional `?ref=` / `?code=`) → WhatsApp CTA includes referral context → Super Admin creates tenant, attaches marketer/discount, snapshots pricing → pending setup commission.
2. **Billing:** Super Admin confirms doctor setup/monthly payment (manual bKash) → commission moves to `owed` → monthly cron creates pending rows → Super Admin marks marketer payout paid.
3. **Patient book:** Tenant site → `/book` wizard → `GET /api/bookings/availability` → `POST /api/bookings` → `BookingService` creates booking inside a DB transaction with `lockForUpdate()` on the bookable (and doctor row when `slot_cap_mode` is `per_doctor_chamber`) → after commit, `SmsService` debits wallet and sends confirmation → redirect to `/bookings/{uuid}` ticket.
4. **Clinic day:** Staff open Live Queue Control → start session for today’s ScheduleSession → call patients → Patient arrived (`in_chamber`) → complete. Daily Roster is a second entry point into the same state machine (Call to Chamber / Mark Completed) and routes through `LiveSessionService`, so both screens always agree. Outdoor TV polls `/api/screen/{session}/{date}`. Ticket page polls `/api/queue/{booking}`.
5. **Lookup:** Patient opens `/portal`, enters phone → exact-match booking list (throttled).
6. **Content:** Admin structures WebPages; staff edits text/images; public `/{slug?}` renders tenant site.
7. **Labs (clinic tier / flag):** Patient can add lab tests + collection slot during booking; ops manage LabTests + LabCollectionSlots in admin.

## Integrations
- **stancl/tenancy** — central path (`InitializeTenancyByPath`) + custom domain (`InitializeTenancyByDomain`); shared DB; `config/tenancy.php` `reserved_path_prefixes` protects `/admin`, `/partner`, assets, etc.
- **Filament 4** — Super Admin + Marketer + Tenant Admin panels
- **WhatsApp** — outbound only via free `wa.me` links (no Business API)
- **SMS gateway** — optional HTTP driver; default `log` for local/tests
- **Mail** — Laravel mailers for Filament password reset (configure `MAIL_*` in production)
- **No payment gateways** — intentionally removed for pay-at-chamber v1; online pre-payment is later-stage only and must not be suggested unless the owner asks
