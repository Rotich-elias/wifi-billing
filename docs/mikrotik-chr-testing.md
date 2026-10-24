# Testing the RouterOS driver with MikroTik CHR

`MockRouterDriver` proves the application flow. It does **not** prove the
RouterOS API code. Run the real driver against MikroTik CHR (Cloud Hosted
Router) in VirtualBox or VMware before pointing it at the production router.

## 1. Run CHR

1. Download the CHR x86_64 image from <https://mikrotik.com/download>.
2. Import the OVA into VirtualBox/VMware, or create a VM with:
   - 1 CPU, 256 MB RAM
   - NIC 1: NAT (WAN)
   - NIC 2: host-only adapter (LAN)
3. Boot and log in as `admin` (no password), then set a password:
   ```routeros
   /user set admin password="choose-a-strong-password"
   ```
4. Give the host-only adapter a LAN address and DHCP:
   ```routeros
   /ip address add address=192.168.88.1/24 interface=ether2
   /ip pool add name=hs-pool ranges=192.168.88.10-192.168.88.254
   /ip dhcp-server add name=hs-dhcp interface=ether2 address-pool=hs-pool disabled=no
   /ip dhcp-server network add address=192.168.88.0/24 gateway=192.168.88.1 dns-server=192.168.88.1
   ```
5. Enable the API. For a lab, plain API on the host-only network is enough:
   ```routeros
   /ip service enable api
   /ip service set api address=192.168.88.0/24
   ```
   For API-SSL, enable `api-ssl` on port 8729 and use
   `ROUTER_API_TLS=true`. A CHR self-signed certificate needs
   `ROUTER_API_TLS_VERIFY=false` in the lab only.
6. Create the hotspot server and profile the app expects:
   ```routeros
   /ip hotspot profile add name=hsprof hotspot-address=192.168.88.1 dns-name=hotspot.local login-by=http-chap,http-pap
   /ip hotspot add name=hotspot1 interface=ether2 address-pool=hs-pool profile=hsprof disabled=no
   ```
   The name must match `ROUTER_HOTSPOT_SERVER` (default `hotspot1`).

## 2. Point the app at CHR

In `.env`:

```env
ROUTER_DRIVER=routeros
ROUTER_HOST=192.168.88.1
ROUTER_API_PORT=8728
ROUTER_API_TLS=false
ROUTER_USER=admin
ROUTER_PASSWORD=choose-a-strong-password
ROUTER_HOTSPOT_SERVER=hotspot1
```

Clear cached config after changing it:

```bash
php artisan config:clear
```

Make sure the app host can reach the CHR host-only network (for example, put
the app host on the same host-only adapter or add a route).

## 3. Run the smoke test

```bash
php artisan router:check
```

Expected:

```text
Driver: routeros
Host:   192.168.88.1:8728
Router connection OK.
Plan:   #1 Day Pass (1h, data limit: 5G)
Creating hotspot user XXXX2345...
User created and read back OK.
Idempotent re-run OK (no duplicate user).
Test user removed.
Router driver check passed.
```

What this proves:

- API login and socket framing
- `getHotspotUser` / `addHotspotUser`
- profile creation (`voucher_plan_<id>`)
- `limit-uptime` and `limit-bytes-total`
- idempotent provisioning
- `removeHotspotUser`

### Inspect the user and profile

```bash
php artisan router:check --keep
```

Then on the router:

```routeros
/ip hotspot user print detail where name~"^[A-Z2-9]{8}$"
/ip hotspot user profile print detail where name~"voucher_plan_"
```

Check:

- `limit-uptime` equals the plan duration (for example `01:00:00`), **not**
  `session_timeout`
- `limit-bytes-total` is in bytes (5G becomes `5368709120`)
- the profile `rate-limit` is `<upload>/<download>`
- the profile `session-timeout` matches the per-login cap, or `00:00:00` when
  the plan has no session cap

Remove the kept user when finished:

```routeros
/ip hotspot user remove [find name="XXXXXXXX"]
```

### Test expiry removal

```bash
php artisan router:check --expiry
```

This creates a voucher that is already past `expires_at`, runs
`ExpireVoucherJob`, and asserts that the voucher becomes `expired` and the
router user is gone.

## 4. Test the full payment flow

1. Create an active plan in the admin panel.
2. Keep `ROUTER_DRIVER=routeros` and run a queue worker:
   ```bash
   php artisan queue:work --tries=3 --timeout=120
   ```
3. Connect a client to the CHR hotspot and open the portal.
4. Complete a sandbox M-Pesa payment.
5. Confirm:
   - the payment becomes `paid`
   - the voucher appears only after the router user exists
   - the code logs in through the hotspot
   - `vouchers:expire` removes the user when `expires_at` passes

## 5. What to watch for

- `!trap` responses usually mean a bad profile/server name, an unsupported
  property, or a duplicate user.
- API-SSL on 8729 needs `ROUTER_API_TLS=true`; plain API on 8728 needs it false.
- If `limit-bytes-total` is rejected, check the RouterOS version and the
  `data_limit` value (the app converts `500M`, `5G`, etc. to bytes).
- Do not use `ROUTER_API_TLS_VERIFY=false` against a production router. Use a
  trusted certificate or keep the API on a firewalled management network.

## 6. Cleanup

Remove the test hotspot server, pool, DHCP server and profile if the VM is
disposable. Stop the VM when finished.
