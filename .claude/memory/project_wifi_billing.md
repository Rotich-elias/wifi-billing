---
name: project-wifi-billing
description: WiFi hotspot voucher billing system built with Laravel + MySQL, MikroTik RouterOS API, M-Pesa Daraja STK Push
metadata:
  type: project
---

Full Laravel 11 application scaffolded at /home/smith/Desktop/wifi-billing.

**Why:** User requested a complete WiFi hotspot voucher billing system.

**Stack:** Laravel 11, PHP 8.4, MySQL, Bootstrap 5 (CDN, no Vite needed), laravel/ui for auth.

**Key files:**
- Services: `app/Services/MikrotikService.php`, `MpesaService.php`, `VoucherService.php`
- Models: Plan, Voucher, Payment, MikrotikSyncLog
- Config: `config/mikrotik.php`, `config/mpesa.php`
- Admin: `resources/views/admin/` (dashboard, plans, vouchers, payments)
- Portal: `resources/views/portal/` (index, pending, status)

**Setup needed:**
1. Create MySQL DB `wifi_billing`, update `.env` credentials
2. `php artisan migrate --seed` (seeds admin user + 5 default plans)
3. Set M-Pesa Daraja keys in `.env`
4. Set MikroTik IP/credentials in `.env`
5. Cron: `* * * * * php artisan schedule:run` (expires vouchers every 5 min)

**Admin login:** admin@wifi.local / admin123 (after seed)

**How to apply:** Reference this when continuing work on this project — migrations, routes, and views are already built.
