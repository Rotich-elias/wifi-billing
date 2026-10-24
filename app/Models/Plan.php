<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'type', 'duration_hours', 'price',
        'bandwidth_up', 'bandwidth_down',
        'session_timeout', 'idle_timeout', 'data_limit',
        'is_active', 'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function getDurationLabelAttribute(): string
    {
        return match ($this->type) {
            'daily' => "{$this->duration_hours}h",
            'weekly' => '7 days',
            'monthly' => '30 days',
            default => "{$this->duration_hours}h",
        };
    }

    /**
     * Total hotspot uptime allowed by the router. It mirrors the plan's
     * wall-clock window as a safety net, so RouterOS cuts the user off even if
     * the app-side expiry job cannot run.
     */
    public function uptimeSeconds(): int
    {
        return max(1, (int) $this->duration_hours * 3600);
    }

    /**
     * Optional per-login session cap. Null/0 means no per-session limit; it is
     * not the total usage allowance (that is uptimeSeconds()).
     */
    public function sessionTimeoutSeconds(): ?int
    {
        $seconds = (int) ($this->session_timeout ?? 0);

        return $seconds > 0 ? $seconds : null;
    }
}
