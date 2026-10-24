<?php

namespace App\Models;

use App\Jobs\ExpireVoucherJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use SoftDeletes;

    /**
     * Uppercase alphabet with no ambiguous characters: 0/O and 1/I.
     */
    public const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 8;

    protected $fillable = [
        'code', 'access_token', 'plan_id', 'status',
        'customer_name', 'customer_phone',
        'activated_at', 'expires_at',
        'mikrotik_user', 'mikrotik_synced', 'mikrotik_synced_at', 'provisioning_error',
        'batch_id',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'mikrotik_synced' => 'boolean',
        'mikrotik_synced_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Voucher $voucher) {
            if (empty($voucher->code)) {
                $voucher->code = self::generateUniqueCode();
            }
            if (empty($voucher->access_token)) {
                $voucher->access_token = Str::random(48);
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;

        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
        } while (self::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Accept the code as typed (spaces, lowercase, legacy dashes).
     */
    public static function normaliseCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function plan(): BelongsTo
    {
        // Plans are soft-deleted so their vouchers and payment history survive.
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(MikrotikSyncLog::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * A code is only safe to show once the router has the user.
     */
    public function isProvisioned(): bool
    {
        return $this->status === 'active'
            && $this->mikrotik_synced
            && ! $this->isExpired();
    }

    public function activate(): void
    {
        // A voucher can only be activated once; this makes callback replays safe.
        if ($this->status !== 'unused') {
            return;
        }

        // Validity is wall-clock: the voucher expires duration_hours after
        // activation, whether or not the customer was online. RouterOS gets
        // the same duration as limit-uptime, but that is only a safety net.
        $this->activated_at = now();
        $this->expires_at = now()->addHours($this->plan->duration_hours);
        $this->status = 'active';
        $this->save();
    }

    public function markProvisioned(?string $routerUser = null): void
    {
        $this->update([
            'mikrotik_synced' => true,
            'mikrotik_synced_at' => now(),
            'mikrotik_user' => $routerUser ?: $this->code,
            'provisioning_error' => null,
        ]);
    }

    public function markProvisioningFailed(string $message): void
    {
        $this->update(['provisioning_error' => Str::limit($message, 500)]);
    }

    /**
     * Build the RouterOS hotspot login redirect for this voucher.
     */
    public function hotspotLoginUrl(?string $loginUrl = null, ?string $origUrl = null): ?string
    {
        if (! self::isSafeLoginUrl($loginUrl)) {
            return null;
        }

        $params = [
            'username' => $this->code,
            'password' => $this->code,
        ];

        // Only forward an http(s) destination; never let a crafted portal link
        // turn the router redirect into a different scheme.
        if ($origUrl && self::isSafeRedirectUrl($origUrl)) {
            $params['dst'] = $origUrl;
        }

        $separator = str_contains($loginUrl, '?') ? '&' : '?';

        return $loginUrl.$separator.http_build_query($params);
    }

    /**
     * The hotspot login URL comes from the client (MikroTik redirect). Only
     * trust hosts we control so a crafted portal link cannot receive a
     * customer's voucher code.
     */
    public static function isSafeLoginUrl(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = rtrim(strtolower(trim((string) ($parts['host'] ?? ''), '[]')), '.');

        if ($host === '') {
            return false;
        }

        $allowed = array_map(
            fn ($value) => strtolower((string) $value),
            array_filter((array) config('router.allowed_login_hosts', [])),
        );

        $allowed[] = strtolower((string) config('router.host', ''));
        $allowed[] = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));

        if (in_array($host, array_filter($allowed), true)) {
            return true;
        }

        // A MikroTik hotspot normally sends its LAN gateway IP (for example
        // 192.168.88.1) or a local DNS name. Those are not public hosts, so
        // they are safe to redirect to without an explicit allowlist entry.
        // Public domains still require ROUTER_ALLOWED_LOGIN_HOSTS.
        return self::isPrivateHost($host);
    }

    private static function isPrivateHost(string $host): bool
    {
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return true;
        }

        // Single-label names such as "hotspot" or "wifi" cannot resolve on the
        // public internet; they are resolved by the hotspot's own DNS.
        if (! str_contains($host, '.')) {
            return true;
        }

        // Local-only DNS suffixes used by hotspot routers.
        foreach (['.local', '.lan', '.home', '.internal', '.localhost'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Private, loopback, link-local and reserved addresses are LAN-only.
        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    public static function isSafeRedirectUrl(?string $url): bool
    {
        if (! $url) {
            return true;
        }

        return in_array(
            strtolower((string) parse_url($url, PHP_URL_SCHEME)),
            ['http', 'https'],
            true,
        );
    }

    public function checkAndExpire(): bool
    {
        if ($this->status !== 'active' || ! $this->isExpired()) {
            return false;
        }

        // Never mutate the status here: the job marks it expired and, crucially,
        // removes the hotspot user from the router.
        ExpireVoucherJob::dispatch($this);

        return true;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'unused' => 'secondary',
            'active' => 'success',
            'expired' => 'danger',
            'revoked' => 'warning',
            default => 'secondary',
        };
    }
}
