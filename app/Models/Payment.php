<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'plan_id', 'voucher_id', 'customer_name', 'phone', 'amount', 'status',
        'mac', 'ip', 'login_url', 'orig_url',
        'merchant_request_id', 'checkout_request_id',
        'mpesa_receipt', 'result_code', 'result_desc',
        'paid_at', 'raw_callback',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_callback' => 'array',
    ];

    public function plan(): BelongsTo
    {
        // Keep showing the plan on old payments after it is soft-deleted.
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PAID => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_EXPIRED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * The RouterOS hotspot login URL for this payment's voucher.
     */
    public function hotspotLoginUrl(): ?string
    {
        if (! $this->login_url || ! $this->voucher) {
            return null;
        }

        return $this->voucher->hotspotLoginUrl($this->login_url, $this->orig_url);
    }

    /**
     * Signed URL used by the browser to poll this payment.
     */
    public function signedStatusUrl(): string
    {
        return URL::temporarySignedRoute(
            'portal.payment.status',
            now()->addHours(24),
            ['payment' => $this->id],
        );
    }

    public function signedShowUrl(): string
    {
        return URL::temporarySignedRoute(
            'portal.payment.show',
            now()->addHours(24),
            ['payment' => $this->id],
        );
    }

    /**
     * Normalise a Kenyan M-Pesa number to 2547XXXXXXXX / 2541XXXXXXXX.
     */
    public static function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            $phone = '254'.$phone;
        } elseif (str_starts_with($phone, '2540')) {
            $phone = '254'.substr($phone, 4);
        }

        return $phone;
    }
}
