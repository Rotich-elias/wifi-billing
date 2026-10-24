# WiFi Billing

Laravel application for selling WiFi hotspot vouchers via M-Pesa (Daraja STK Push),
provisioning users on MikroTik RouterOS, and optionally sending codes by SMS
(Africa's Talking, behind a feature flag).

## Requirements

- PHP 8.3+ with `pdo_mysql` (recommended for production) or `pdo_sqlite`
- MySQL 8+ or MariaDB (PostgreSQL also works)
- Composer
- A MikroTik router with the RouterOS API enabled (preferably API-SSL)
- M-Pesa Daraja credentials (sandbox or production)
- Africa's Talking credentials only if `SMS_ENABLED=true`
- Cron and a supervised queue worker

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Edit .env: database, router, M-Pesa, admin password
php artisan migrate --seed
```

The seeder creates the admin account from `ADMIN_EMAIL` / `ADMIN_PASSWORD`. If
`ADMIN_PASSWORD` is empty it generates one and prints it once.

## Required environment

See `.env.example`. The important groups are:

| Group | Keys |
|---|---|
| App | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` (LAN portal URL customers use) |
| Admin | `ADMIN_EMAIL`, `ADMIN_PASSWORD` |
| Router | `ROUTER_DRIVER=mock\|routeros`, `ROUTER_HOST`, `ROUTER_API_PORT`, `ROUTER_USER`, `ROUTER_PASSWORD`, `ROUTER_HOTSPOT_SERVER`, optional `ROUTER_ALLOWED_LOGIN_HOSTS` |
| M-Pesa | `MPESA_ENV`, `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY` |
| Callback | `MPESA_CALLBACK_BASE_URL` (public HTTPS tunnel), `MPESA_CALLBACK_SECRET`, optional `MPESA_ALLOWED_IPS` |
| SMS | `SMS_ENABLED=false`, `AT_USERNAME`, `AT_API_KEY`, `AT_SENDER_ID` |

The callback URL is built as:

```text
{MPESA_CALLBACK_BASE_URL}/api/mpesa/callback/{MPESA_CALLBACK_SECRET}
```

The secret is part of the path, not a query string, so it does not appear in
proxy query logs. Use a long random value.

## Development

Use sandbox Daraja and the mock router:

```env
ROUTER_DRIVER=mock
MPESA_ENV=sandbox
SMS_ENABLED=false
```

The mock driver stores hotspot users in the `mock_router_users` table, so the
whole flow works without a router. Expose the callback with ngrok (or another
HTTPS tunnel) and set `MPESA_CALLBACK_BASE_URL` to that URL.

## Payment flow

1. The MikroTik hotspot redirects the customer to the portal with
   `mac`, `ip`, `link-login-only` and `link-orig`.
2. The portal shows active plans.
3. The customer picks a plan and enters a Safaricom number.
4. A `pending` payment is created first, storing the plan, phone, amount, MAC,
   IP, login URL and original URL. Only then is the STK push sent.
5. The browser polls the signed `GET /payments/{id}/status` endpoint every
   2.5 seconds. A bare payment ID cannot be used without the signature.
6. Daraja calls `POST /api/mpesa/callback/{secret}`. The callback is always
   acknowledged with HTTP 200 and `{"ResultCode":0,"ResultDesc":"Accepted"}`,
   including unknown or already-processed requests.
7. On success the callback verifies the amount, stores the unique M-Pesa
   receipt, marks the payment `paid`, and creates the voucher in one DB
   transaction.
8. A queued provisioning job creates the hotspot user (`name=code`,
   `password=code`, plan profile, uptime and optional data limit).
9. The portal only reveals the code after the router user exists, then redirects
   the browser to `{link-login-only}?username=CODE&password=CODE&dst={link-orig}`.
10. Returning customers can use the "Have a code?" box.

Payment states: `pending -> paid | failed | expired`.
Voucher states: `unused -> active -> expired` (plus `revoked` for admin action).

## Recovery and expiry

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Run a supervised queue worker:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Scheduled jobs:

- `payments:recover` every minute — queries Daraja for pending payments older
  than 60 seconds and expires payments older than about 5 minutes.
- `vouchers:expire` every minute — marks overdue vouchers expired and removes
  the hotspot user.
- `vouchers:provision-pending` every 5 minutes — re-queues active vouchers that
  are not yet on the router (for example after a router outage).
- `vouchers:reconcile` daily at 03:00 — reports router/DB drift.

To clean up stale router users:

```bash
php artisan vouchers:reconcile --fix
```

## Production deployment

The customer portal and admin panel should be reachable on the LAN. Only the
M-Pesa callback needs to be public. A typical setup:

```text
Safaricom Daraja
      │ HTTPS
      ▼
Cloudflare Tunnel (callback route only)
      │
      ▼
Laravel app on the LAN ──► MikroTik RouterOS API
```

- Use `ROUTER_DRIVER=routeros` and keep the RouterOS API firewalled to the app
  server's IP with a least-privilege API user.
- Set `APP_URL` to the LAN portal address and `MPESA_CALLBACK_BASE_URL` to the
  public tunnel URL. Do not point `APP_URL` at the tunnel: signed payment URLs
  would then be generated for the public host.
- Use a real domain for the callback URL; free tunnel URLs change.
- Set `APP_ENV=production`, `APP_DEBUG=false`, and use HTTPS session cookies
  only if the LAN portal is served over HTTPS. On plain-HTTP LAN deployments
  set `SESSION_SECURE_COOKIE=false`.
- Run Nginx/Apache + PHP-FPM, not `php artisan serve`.
- Supervise the queue worker and scheduler with systemd or Supervisor.

## Tests

```bash
php artisan test
composer audit
vendor/bin/pint --test
```

The feature tests cover successful payment, cancelled STK, duplicate callback,
missing receipts, lost callback recovery, router outage, wrong amount, signed
polling, returning customer codes, unsafe login hosts, voucher expiry, voucher
code format and admin authorization.

## Security notes

- Admin routes require both `auth` and the `is_admin` flag.
- M-Pesa callbacks require the path secret; set `MPESA_ALLOWED_IPS` to Safaricom
  CIDRs in production.
- Vouchers are never addressable by sequential ID.
- The `link-login-only` host is validated against `ROUTER_HOST` and
  `ROUTER_ALLOWED_LOGIN_HOSTS` before a code is appended, so a crafted portal
  link cannot redirect a voucher code to another site.
- Rate limits apply to checkout and code-entry endpoints.
- Callback payloads are logged, but credentials and callback secrets are not.
- Rotate `APP_KEY`, M-Pesa, RouterOS and SMS credentials after any suspected
  exposure. `APP_KEY` rotation invalidates sessions and signed URLs.
- Keep `.env` and the database file at mode `600`.
- For local development over plain HTTP, temporarily set
  `SESSION_SECURE_COOKIE=false`; keep it `true` behind HTTPS.

## Deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`
- [ ] `ROUTER_DRIVER=routeros` and router API reachable from the app server
- [ ] `MPESA_ENV=production` and production Daraja credentials
- [ ] `MPESA_CALLBACK_BASE_URL` is the stable public HTTPS URL
- [ ] `SMS_ENABLED=false` until the SMS phase is ready
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Queue worker supervised and running
- [ ] Cron `schedule:run` installed
- [ ] HTTPS + secure/encrypted session cookies
- [ ] Backups scheduled and restore tested
- [ ] `composer audit` clean
- [ ] Admin password changed; `ADMIN_PASSWORD` not left in `.env` if unused
