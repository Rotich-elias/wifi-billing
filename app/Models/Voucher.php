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

    protected $fillable = [
        'code', 'access_token', 'plan_id', 'status',
        'customer_name', 'customer_phone',
        'activated_at', 'expires_at',
        'mikrotik_user', 'mikrotik_synced', 'mikrotik_synced_at',
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
        do {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
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

    public function activate(): void
    {
        // A voucher can only be activated once; this makes callback replays safe.
        if ($this->status !== 'unused') {
            return;
        }

        $this->activated_at = now();
        $this->expires_at = now()->addHours($this->plan->duration_hours);
        $this->status = 'active';
        $this->save();
    }

    public function checkAndExpire(): bool
    {
        if ($this->status !== 'active' || ! $this->isExpired()) {
            return false;
        }

        // Never mutate the status here: the job marks it expired and, crucially,
        // removes the hotspot user from MikroTik.
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
