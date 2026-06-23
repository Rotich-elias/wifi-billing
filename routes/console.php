<?php

use App\Models\Voucher;
use App\Services\SmsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('vouchers:expire')->everyFiveMinutes();

// Send expiry SMS 1 hour before voucher expires
Schedule::call(function () {
    $soon = Voucher::where('status', 'active')
        ->whereBetween('expires_at', [now()->addMinutes(55), now()->addMinutes(65)])
        ->whereNotNull('customer_phone')
        ->get();

    $sms = app(SmsService::class);
    foreach ($soon as $voucher) {
        $sms->sendExpiryWarning($voucher);
    }
})->everyThirtyMinutes()->name('sms-expiry-warning')->withoutOverlapping();
