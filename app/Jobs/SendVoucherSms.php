<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendVoucherSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly Voucher $voucher) {}

    public function handle(SmsService $sms): void
    {
        if (! config('sms.enabled') || ! $this->voucher->customer_phone) {
            return;
        }

        try {
            if (! $sms->sendVoucherCode($this->voucher)) {
                throw new \RuntimeException('SMS provider did not accept the message.');
            }
        } catch (Throwable $e) {
            // SMS must never block activation or portal display. Rethrow so
            // the queued job retries with backoff.
            Log::error('Voucher SMS failed', [
                'voucher_id' => $this->voucher->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
