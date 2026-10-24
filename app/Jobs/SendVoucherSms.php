<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendVoucherSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly Voucher $voucher) {}

    public function handle(SmsService $sms): void
    {
        $sms->sendVoucherCode($this->voucher);
    }
}
