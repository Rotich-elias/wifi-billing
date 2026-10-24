# WiFi Billing — Remaining Security & Reliability Work

**Audit date:** 2026-10-24
**Scope:** Laravel application in this repo (security, payment flow, voucher lifecycle, operations). Host OS / network not included.
**Status:** 35 of 46 audit items fixed. The 11 remaining items are below.

**Legend:** 🔴 Critical · 🟠 High · 🟡 Medium · 🔵 Low

---

## Progress

| Phase | Items | Done | Remaining |
|---|---:|---:|---:|
| P0 — Stop the bleeding | 9 | 8 | 1 |
| P1 — Payment & data integrity | 25 | 22 | 3 |
| P2 — Testing, ops & hardening | 12 | 5 | 7 |
| **Total** | **46** | **35** | **11** |

---

## P0 — Stop the bleeding

- [ ] **P0-09 🟠 Rotate potentially exposed secrets** — provider-side rotation pending
  - Where: `.env` (M-Pesa, MikroTik, Africa's Talking)
  - Problem: debug mode was enabled on a public URL, so treat integration secrets as potentially leaked.
  - [ ] Rotate the M-Pesa consumer key/secret/passkey in the Daraja portal
  - [ ] Rotate the MikroTik API password and create a least-privilege API user
  - [ ] Rotate the Africa's Talking API key
  - [ ] Confirm all integrations work with the new credentials
  - Done when: all integrations work with new credentials and old ones are revoked.

---

## P1 — Payment, data integrity & reliability

- [ ] **P1-07 🟠 Encrypt the RouterOS API**
  - Where: `config/mikrotik.php`, `.env` (`MIKROTIK_PORT=8728`)
  - Problem: plaintext API credentials and commands over the network.
  - Fix: use API-SSL (8729) or a tunnel/VPN, with certificate verification; restrict the API to the billing server's IP; use a least-privilege RouterOS user.
  - Done when: port 8728 is closed and 8729/TLS is in use.

- [ ] **P1-22 🟡 Add a stable HTTPS callback URL**
  - Where: `.env` (`MPESA_CALLBACK_URL="${APP_URL}/api/mpesa/callback?secret=${MPESA_CALLBACK_SECRET}"`)
  - Problem: the URL depends on a free ngrok host that changes and may not be reachable.
  - Fix: point at the production domain with a valid certificate; verify reachability from Safaricom; document the rotation process if tunnels are used for testing.
  - Done when: `MPESA_CALLBACK_URL` is the production HTTPS URL and a real sandbox callback succeeds.

- [ ] **P1-25 🟡 Add router↔DB monitoring**
  - Where: operations
  - Problem: sync failures currently produce no alert.
  - Fix: alert on `mikrotik_sync_logs.status='failed'`, failed jobs, and completed payments without activation.
  - Done when: a forced failure triggers a notification.

---

## P2 — Testing, ops & hardening

- [ ] **P2-05 🔵 Back up and retain data**
  - Where: operations
  - Fix: scheduled SQLite/MySQL backups, log rotation/retention, and a tested restore procedure; keep financial records immutable.
  - Done when: a restore drill succeeds and retention is defined.

- [ ] **P2-06 🔵 Harden proxy/host/HTTPS handling**
  - Where: `bootstrap/app.php`
  - Fix: configure `TrustProxies` for the real proxy/CDN, `TrustHosts`, and HSTS at the edge; ensure generated URLs are HTTPS.
  - Done when: absolute URLs are HTTPS and foreign Host headers are rejected.

- [ ] **P2-07 🔵 Add admin 2FA and session controls**
  - Where: auth
  - Fix: optional TOTP for admins, shorter admin session lifetime, logout-all-devices, audit log of admin actions (voucher revoke/delete, plan changes).
  - Done when: admin actions are attributable and protected by a second factor.

- [ ] **P2-08 🔵 Remove unused scaffolding**
  - Where: `app/Http/Controllers/HomeController.php`, `resources/views/home.blade.php`, `resources/views/welcome.blade.php`, `app/Http/Controllers/Auth/RegisterController.php`
  - Fix: delete or explicitly disable; ensure no dormant registration route can be re-enabled accidentally.
  - Done when: no dead auth/registration surface remains.

- [ ] **P2-10 🔵 Make printing safe and bounded**
  - Where: `app/Http/Controllers/Admin/VoucherController.php`
  - Problem: `printFiltered` silently caps at 200 and `printBatch` accepts any batch ID.
  - Fix: validate/surface the cap, validate the batch ID, and avoid loading unbounded sets into Dompdf.
  - Done when: oversized print requests are rejected or clearly reported.

- [ ] **P2-11 🔵 Review voucher code strength**
  - Where: `app/Models/Voucher.php`
  - Problem: 8 uppercase-alphanumeric characters (~2.8e12) is workable only if lookups are throttled; codes are also used as router passwords.
  - Fix: after P1-21, consider 10–12 characters or a checksum to reduce typo/guess risk.
  - Done when: code length/entropy is documented and lookup throttling is in place.

- [ ] **P2-12 🔵 Add basic observability**
  - Where: operations
  - Fix: health checks (`/up` already exists) for DB/queue, uptime and error-rate alerts, and a dashboard for pending/paid/failed payments.
  - Done when: an outage or payment gap is detected without a customer report.

---

## Outstanding release gate

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, real HTTPS `APP_URL`
- [ ] Expired/revoked/deleted vouchers are removed from MikroTik
- [ ] Scheduler + queue worker running with alerting
- [ ] Backups and restore drill completed

---

## Verification commands

```bash
# Dependencies
composer update
composer audit

# Tests / style
php artisan test
vendor/bin/pint --test

# Routes and middleware
php artisan route:list --except-vendor
php artisan route:list --json --path=api
```
