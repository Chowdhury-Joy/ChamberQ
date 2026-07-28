# Doctor Gemini

Multi-tenant SaaS for Bangladesh solo doctors and clinics: branded patient site, online serial booking, live waiting-room queue, and Filament admin.

Patients pay at the chamber. WhatsApp is free `wa.me` sharing (no Business API). SMS confirmations use a **prepaid credit wallet** (no included free SMS) — Super Admin tops up tenants; each successful confirm debits 1 credit.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # if using sqlite
php artisan migrate --seed
php artisan serve
```

Optional: `composer run dev` for server + queue + Vite.

### Domains (tenancy)

Set `CENTRAL_DOMAINS` in `.env` (comma-separated). Defaults:

```
CENTRAL_DOMAINS=127.0.0.1,localhost
```

- **Central** (marketing + Super Admin): `http://localhost/` and `http://localhost/admin`
- **Tenant example** (after seed): `http://solo.localhost/` and `http://solo.localhost/admin`

Map tenant hosts in `/etc/hosts` if needed, e.g. `127.0.0.1 solo.localhost`.

### Demo accounts (seeder)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | (see seeder) | `password` |
| Solo admin | `admin@solo.com` | `password` |

Change these before any shared or production environment.

## Tests

```bash
php artisan test
```

CI runs the same suite on push/PR (`.github/workflows/tests.yml`).

## SMS confirmations

Prepaid wallet only — no free monthly allowance.

| Setting | Notes |
|---------|--------|
| `SMS_DRIVER=log` | Default; logs and succeeds (local/tests) |
| `SMS_DRIVER=http` | POST JSON to `SMS_HTTP_URL` with `api_key`, `to`, `message`, `sender` |
| `SMS_ENABLED=false` | Disables sends (no debit) |

Super Admin → Tenant → **Top up SMS** or set **SMS Credits** on the form. Each successful booking confirmation debits **1** credit; empty wallet skips SMS but the booking still succeeds.

## Soft-launch deploy checklist

1. **Env**
   - `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY`
   - Real `APP_URL` (HTTPS)
   - `CENTRAL_DOMAINS=yourdomain.com,www.yourdomain.com` (no tenant chamber hosts)
   - MySQL (or Postgres) credentials — not sqlite
   - Real mail (`MAIL_*`) so Filament password reset works
   - Marketing WhatsApp / pricing vars as needed

2. **App**
   - `composer install --no-dev --optimize-autoloader`
   - `php artisan migrate --force`
   - `php artisan config:cache` and `route:cache` / `view:cache` as appropriate
   - `php artisan storage:link` if logos/uploads are used

3. **Web server**
   - Point central + each tenant domain (or wildcard) at `public/`
   - HTTPS certificates for central and tenant hosts
   - Health check: `GET /up`

4. **Ops**
   - Create tenants in Super Admin; set `billing_status` carefully (`past_due` / `suspended` / `read_only` close online booking)
   - Rotate seeded passwords
   - Queue worker only if you add jobs later (`QUEUE_CONNECTION=database` is prepared)
   - Backups for the shared database

5. **Smoke**
   - Central landing loads
   - Tenant book → ticket → portal
   - Live queue screen + admin login
   - Password reset email arrives for a staff user

## License

Proprietary application code unless otherwise noted. Laravel framework is MIT.
