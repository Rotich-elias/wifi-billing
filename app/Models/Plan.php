<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
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
}
