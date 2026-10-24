# WiFi Billing

Laravel application for selling WiFi hotspot vouchers via M-Pesa (Daraja STK Push),
provisioning users on MikroTik RouterOS, and optionally sending codes by SMS
(Africa's Talking, behind a feature flag).

## Requirements

- PHP 8.3+ with `pdo_mysql` (recommended for production) or `pdo_sqlite`
- MySQL 8+ or MariaDB (PostgreSQL also works)
- Composer
- Node 20+ and npm (to build the self-hosted Bootstrap assets)
- A MikroTik router with the RouterOS API enabled (preferably API-SSL)
- M-Pesa Daraja credentials (sandbox or production)
- Africa's Talking credentials only if `SMS_ENABLED=true`
- Cron/systemd (or containers) for the scheduler, and a supervised queue worker

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Edit .env: database, router, M-Pesa, admin password
php artisan migrate --seed
npm ci
npm run build
```

`npm run build` writes `public/build`. The customer portal, admin panel and
login page use those local assets. They do not load Bootstrap or icons from a
CDN, because a pre-login hotspot client has no internet access yet.

### Branding

The Kirwa Net mark and lockup live in `public/images/`; favicons, the Apple
touch icon and `site.webmanifest` are at the root of `public/`. The name shown
next to the logo comes from `APP_NAME`, and the voucher PDF uses
`BUSINESS_NAME`. To rebrand, replace those files in place (keep the filenames)
and update the two environment values.

The seeder creates the admin account from `ADMIN_EMAIL` / `ADMIN_PASSWORD`. If
`ADMIN_PASSWORD` is empty it generates one and prints it once.

## Required environment

See `.env.example`. The important groups are:

| Group | Keys |
|---|---|
| App | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` (LAN portal URL customers use) |
| Admin | `ADMIN_EMAIL`, `ADMIN_PASSWORD` |
| Router | `ROUTER_DRIVER=mock\|routeros`, `ROUTER_HOST`, `ROUTER_API_PORT`, `ROUTER_API_TLS`, `ROUTER_API_TLS_VERIFY`, `ROUTER_USER`, `ROUTER_PASSWORD`, `ROUTER_HOTSPOT_SERVER`, optional `ROUTER_ALLOWED_LOGIN_HOSTS` |
| M-Pesa | `MPESA_ENV`, `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY` |
| Callback | `MPESA_CALLBACK_BASE_URL` (public HTTPS tunnel), `MPESA_CALLBACK_SECRET`, optional `MPESA_ALLOWED_IPS` |
| Proxy | `TRUSTED_PROXIES` (the local cloudflared/ngrok peer, not a public range) |
| SMS | `SMS_ENABLED=false`, `AT_USERNAME`, `AT_API_KEY`, `AT_SENDER_ID` |

The callback URL is built as:

```text
{MPESA_CALLBACK_BASE_URL}/api/mpesa/callback/{MPESA_CALLBACK_SECRET}
```

The secret is part of the path, not a query string, so it does not appear in
proxy query logs. Use a long random value.

### Hotspot login host

MikroTik sends `link-login-only` with the hotspot gateway address, which is not
necessarily the same as `ROUTER_HOST` (the API address). Check what the hotspot
actually sends before going live:

```bash
php artisan hotspot:hosts
php artisan hotspot:hosts --url="http://192.168.88.1/login"
```

The first command groups the `link-login-only` hosts recorded on portal opens
and payments. Open the portal once from a real hotspot client, then run it. If
a public host is reported as rejected, add it:

```env
ROUTER_ALLOWED_LOGIN_HOSTS=hotspot.example.com,portal.example.com
```

Private/LAN IPs (`10/8`, `172.16/12`, `192.168/16`, loopback, link-local),
single-label names such as `hotspot`, and local suffixes such as `.local`,
`.lan`, `.home` and `.internal` are accepted automatically. Public domains and
public IPs require an explicit allowlist entry.

### Callback IP allowlist

`MPESA_ALLOWED_IPS` only works when the real client IP is visible. Behind
Cloudflare Tunnel the TCP peer is `cloudflared`, so the app reads
`CF-Connecting-IP` **only** when the request comes from `TRUSTED_PROXIES`.
If cloudflared runs in Docker, set `TRUSTED_PROXIES` to the Docker
gateway/container address the app sees, not `127.0.0.1`.

If you cannot guarantee that header, leave `MPESA_ALLOWED_IPS` empty. The path
secret, amount check and unique receipt check are the primary controls.

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

Run `npm run dev` while editing Blade/CSS, or `npm run build` to produce the
same local assets used in production.

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
   The login host must be allowlisted, private/local, or `ROUTER_HOST`.
10. Returning customers can use the "Have a code?" box.

Payment states: `pending -> paid | failed | expired`.
Voucher states: `unused -> active -> expired` (plus `revoked` for admin action).

## Recovery, expiry and background workers

Lost callbacks are recovered by the scheduler. Provisioning, expiry and SMS
warnings run on the queue. **Both must stay up in production**: a cron entry or
scheduler service plus a supervised queue worker.

Use one of the following, not both:

```bash
# Option A: systemd (files in deploy/systemd)
sudo cp deploy/systemd/wifi-billing-*.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now wifi-billing-queue wifi-billing-scheduler

# Option B: cron + Supervisor (files in deploy/cron and deploy/supervisor)
sudo cp deploy/cron/wifi-billing /etc/cron.d/wifi-billing
sudo cp deploy/supervisor/wifi-billing.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update

# Option C: containers (deploy/docker-compose.workers.yml)
docker compose -f deploy/docker-compose.workers.yml up -d
```

The worker and scheduler units use `Restart=always`; the Compose example uses
`restart: unless-stopped`. Create `/var/log/wifi-billing` and make it writable
by `www-data`.

Scheduled jobs:

- `payments:recover` every minute — queries Daraja for pending payments older
  than 60 seconds and expires payments older than about 5 minutes. If the Daraja
  query itself fails (for example `429` or `500`), the payment is expired once
  it passes that grace period, so it is not retried forever. Pending rows older
  than 60 minutes are expired without querying Daraja at all.
- `vouchers:expire` every minute — marks overdue vouchers expired, removes the
  hotspot user, and retries removal for expired/revoked vouchers still marked
  as synced.
- `vouchers:provision-pending` every 5 minutes — re-queues active vouchers that
  are not yet on the router (for example after a router outage).
- `vouchers:reconcile` daily at 03:00 — reports router/DB drift.

### Expiry semantics

Voucher validity is **wall-clock**, not accumulated browsing time:

- `Voucher::activate()` sets `expires_at = activated_at + plan.duration_hours`.
- `vouchers:expire` selects `status = active` with `expires_at < now()`, plus
  `expired`/`revoked` rows still marked `mikrotik_synced` so a failed removal is
  retried.
- `ExpireVoucherJob` marks the voucher `expired` and removes the router user
  (`/ip/hotspot/user/remove`). The removal is retried and is safe to repeat.
- RouterOS also gets `limit-uptime = duration_hours` as a safety net, so the
  router stops the user even if the job cannot run.

`plan.session_timeout` is a **per-login** cap (`session-timeout` in the hotspot
profile), not total usage. It is not used for expiry. A plan described as
"1 hour of internet use" is therefore implemented as "valid for 1 hour after
activation"; if you need cumulative-usage billing, that is a different product
and needs usage accounting from RouterOS.

To clean up stale router users:

```bash
php artisan vouchers:reconcile --fix
```

### Deleting plans

Plans are soft-deleted. Deleting a plan removes it from the admin and portal
lists, but keeps its vouchers and payment history so existing customer codes
continue to work. To restore an archived plan:

```bash
php artisan tinker --execute="App\Models\Plan::withTrashed()->find(ID)->restore();"
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
  server's IP with a least-privilege API user. Prefer API-SSL
  (`ROUTER_API_TLS=true`, port `8729`) so the API password is not sent in clear.
- Set `APP_URL` to the LAN portal address and `MPESA_CALLBACK_BASE_URL` to the
  public tunnel URL. Do not point `APP_URL` at the tunnel: signed payment URLs
  would then be generated for the public host.
- Use a real domain for the callback URL; free tunnel URLs change.
- Serve the portal over **HTTP on the LAN**, not HTTPS, so captive-portal
  detection and the MikroTik redirect behave normally. Keep the callback on
  HTTPS through the tunnel. Set `SESSION_SECURE_COOKIE=false` for the HTTP
  portal and `SESSION_ENCRYPT=true`.
- Expose only `/api/mpesa/callback/{secret}` through Cloudflare Tunnel. Do not
  route the customer portal or `/admin` through the public tunnel.
- Run Nginx/Apache + PHP-FPM, not `php artisan serve`.
- Build assets with `npm ci && npm run build`; do not rely on a CDN.
- Keep the scheduler and queue worker running (see above). Without them, lost
  callbacks are never recovered and paid vouchers may never reach the router.
- Test the real driver against MikroTik CHR before touching production; see
  [`docs/mikrotik-chr-testing.md`](docs/mikrotik-chr-testing.md).

## Testing the RouterOS driver

The mock driver is not enough to prove the RouterOS API code works. Run
MikroTik CHR in VirtualBox or VMware, point `ROUTER_DRIVER=routeros` at it, and
use the built-in smoke test:

```bash
php artisan router:check
php artisan router:check --expiry
php artisan router:check --keep   # inspect the user/profile, then remove it
```

See [`docs/mikrotik-chr-testing.md`](docs/mikrotik-chr-testing.md) for the CHR
setup, expected output, data-limit checks and cleanup.

## Tests

```bash
php artisan test
composer audit
vendor/bin/pint --test
```

The feature tests cover successful payment, cancelled STK, duplicate callback,
missing receipts, lost callback recovery, router outage, wrong amount, signed
polling, returning customer codes, unsafe login hosts, Cloudflare callback IPs,
voucher expiry semantics, router uptime limits, voucher code format and admin
authorization.

## Security notes

- Admin routes require both `auth` and the `is_admin` flag.
- M-Pesa callbacks require the path secret. `MPESA_ALLOWED_IPS` is optional;
  behind Cloudflare Tunnel it only works if `TRUSTED_PROXIES` is correct and
  `CF-Connecting-IP` is present. Otherwise rely on the secret, amount and
  unique-receipt checks.
- Vouchers are never addressable by sequential ID.
- The `link-login-only` host is validated against `ROUTER_HOST`,
  `ROUTER_ALLOWED_LOGIN_HOSTS`, and the private/local-host rules before a code
  is appended, so a crafted portal link cannot redirect a voucher code to a
  public attacker-controlled site.
- Rate limits apply to checkout and code-entry endpoints.
- Callback payloads are logged, but credentials and callback secrets are not.
- Rotate `APP_KEY`, M-Pesa, RouterOS and SMS credentials after any suspected
  exposure. `APP_KEY` rotation invalidates sessions and signed URLs.
- Keep `.env` and the database file at mode `600`.
- For local development over plain HTTP, temporarily set
  `SESSION_SECURE_COOKIE=false`; keep it `true` behind HTTPS.

## Deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`
- [ ] `ROUTER_DRIVER=routeros`, API reachable from the app server, API firewalled
- [ ] `php artisan hotspot:hosts` shows the real `link-login-only` host as accepted
- [ ] RouterOS driver tested against CHR (`router:check`, `router:check --expiry`)
- [ ] `MPESA_ENV=production` and production Daraja credentials
- [ ] `MPESA_CALLBACK_BASE_URL` is the stable public HTTPS URL
- [ ] Cloudflare Tunnel exposes only the callback route, not the portal/admin
- [ ] `TRUSTED_PROXIES` points at the local proxy peer; `MPESA_ALLOWED_IPS` is correct or empty
- [ ] `SMS_ENABLED=false` until the SMS phase is ready
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm ci && npm run build` (no CDN dependency)
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Queue worker supervised and running
- [ ] Scheduler running (`schedule:work` service or cron `schedule:run`)
- [ ] Portal is HTTP-only on the LAN; callback is HTTPS
- [ ] Backups scheduled and restore tested
- [ ] `composer audit` clean
- [ ] Admin password changed; `ADMIN_PASSWORD` not left in `.env` if unused
