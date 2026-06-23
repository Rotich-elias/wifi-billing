<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Voucher extends Model
{
    protected $fillable = [
        'code', 'plan_id', 'status',
        'customer_name', 'customer_phone',
        'activated_at', 'expires_at',
        'mikrotik_user', 'mikrotik_synced', 'mikrotik_synced_at',
        'batch_id',
    ];

    protected $casts = [
        'activated_at'      => 'datetime',
        'expires_at'        => 'datetime',
        'mikrotik_synced'   => 'boolean',
        'mikrotik_synced_at'=> 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Voucher $voucher) {
            if (empty($voucher->code)) {
                $voucher->code = self::generateUniqueCode();
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
        $this->activated_at = now();
        $this->expires_at   = now()->addHours($this->plan->duration_hours);
        $this->status       = 'active';
        $this->save();
    }

    public function checkAndExpire(): bool
    {
        if ($this->status === 'active' && $this->isExpired()) {
            $this->update(['status' => 'expired']);
            return true;
        }
        return false;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'unused'  => 'secondary',
            'active'  => 'success',
            'expired' => 'danger',
            'revoked' => 'warning',
            default   => 'secondary',
        };
    }
}
