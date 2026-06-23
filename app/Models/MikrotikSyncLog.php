<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikSyncLog extends Model
{
    protected $fillable = [
        'voucher_id', 'action', 'status', 'message', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
