# WiFi Billing

Laravel application for selling WiFi hotspot vouchers via M-Pesa (Daraja STK Push), provisioning users on MikroTik RouterOS, and sending voucher codes by SMS (Africa's Talking).

## Requirements

- PHP 8.3+ with `pdo_sqlite` or `pdo_mysql`
- Composer
- A MikroTik router with the API (preferably API-SSL, port 8729) enabled
- M-Pesa Daraja credentials (sandbox or production)
- Africa's Talking credentials (optional; SMS is skipped without them)
- Cron and a supervised queue worker

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Edit .env: database, MikroTik, M-Pesa, Africa's Talking, ADMIN_PASSWORD
php artisan migrate --seed
npm install && npm run build   # only if you use the Vite assets
```

The seeder creates the admin account from `ADMIN_EMAIL` / `ADMIN_PASSWORD`. If `ADMIN_PASSWORD` is empty it generates one and prints it once — save it immediately and change it after first login.

## Required environment

See `.env.example`. The important groups are:

| Group | Keys |
|---|---|
| App | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` (public HTTPS) |
| Admin | `ADMIN_EMAIL`, `ADMIN_PASSWORD` |
| MikroTik | `MIKROTIK_HOST`, `MIKROTIK_PORT`, `MIKROTIK_USER`, `MIKROTIK_PASSWORD`, `MIKROTIK_HOTSPOT_SERVER` |
| M-Pesa | `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`, `MPESA_ENVIRONMENT` |
| Callback | `MPESA_CALLBACK_SECRET`, `MPESA_CALLBACK_URL`, optional `MPESA_ALLOWED_IPS` |
| SMS | `AT_USERNAME`, `AT_API_KEY`, `AT_SENDER_ID` |

`MPESA_CALLBACK_URL` must be a stable public HTTPS URL reachable by Safaricom and must include `?secret=...`. Each STK request sends this URL to Daraja, so no portal change is needed when the secret rotates — only a redeploy/config clear.

## Payment flow

1. Customer picks an active plan and enters a phone number.
2. A `pending` payment and an `unused` voucher are created before the STK push.
3. Daraja calls `POST /api/mpesa/callback?secret=...`.
4. The callback is rejected unless the secret matches (and the IP is allowed, if configured), the payment is still pending, and the callback amount matches the charged amount.
5. The payment becomes `completed`, the voucher is activated once, and router/SMS jobs are queued.
6. The customer receives the code by SMS and can open the status page from the link shown after purchase.

The status page is addressed by an unguessable per-voucher token (`/voucher/{token}/status`), never by numeric ID.

## Expiry, queue and reconciliation

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Run a supervised queue worker:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Scheduled jobs:

- `vouchers:expire` every 5 minutes — marks overdue vouchers expired and removes the hotspot user.
- `vouchers:reconcile` daily at 03:00 — reports router/DB drift and completed payments with unused vouchers.

To clean up stale router users (users with no valid voucher):

```bash
php artisan vouchers:reconcile --fix
```

## Tests

```bash
php artisan test
composer audit
vendor/bin/pint --test
```

## Security notes

- Admin routes require both `auth` and the `is_admin` flag.
- M-Pesa callbacks require the shared secret; set `MPESA_ALLOWED_IPS` to Safaricom CIDRs in production.
- Vouchers are never addressable by sequential ID.
- Rotate `APP_KEY`, M-Pesa, MikroTik and SMS credentials after any suspected exposure. `APP_KEY` rotation invalidates sessions and signed URLs.
- Prefer RouterOS API-SSL (8729) and a least-privilege API user; keep the API firewalled to the billing server.
- Keep `.env` and the SQLite database at mode `600`.
- For local development over plain HTTP, temporarily set `SESSION_SECURE_COOKIE=false`; keep it `true` behind HTTPS.

## Deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Queue worker supervised and running
- [ ] Cron `schedule:run` installed
- [ ] HTTPS + secure/encrypted session cookies
- [ ] Backups scheduled and restore tested
- [ ] `composer audit` clean
- [ ] Admin password changed; `ADMIN_PASSWORD` not left in `.env` if unused
