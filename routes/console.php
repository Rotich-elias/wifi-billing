<?php

use App\Models\Voucher;
use App\Services\SmsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconcile pending STK requests with Daraja and expire stale payments.
Schedule::command('payments:recover')->everyMinute()->withoutOverlapping();

// Mark overdue vouchers expired and remove them from the router.
Schedule::command('vouchers:expire')->everyMinute()->withoutOverlapping();

// Self-heal vouchers whose provisioning job exhausted its retries.
Schedule::command('vouchers:provision-pending')->everyFiveMinutes()->withoutOverlapping();

// Report router/DB drift daily (run manually with --fix to clean up).
Schedule::command('vouchers:reconcile')->dailyAt('03:00')->withoutOverlapping();

// Send an expiry SMS only when the SMS feature is enabled.
Schedule::call(function () {
    $soon = Voucher::where('status', 'active')
        ->whereBetween('expires_at', [now()->addMinutes(55), now()->addMinutes(65)])
        ->whereNotNull('customer_phone')
        ->get();

    $sms = app(SmsService::class);
    foreach ($soon as $voucher) {
        $sms->sendExpiryWarning($voucher);
    }
})->everyThirtyMinutes()
    ->name('sms-expiry-warning')
    ->when(fn () => config('sms.enabled'))
    ->withoutOverlapping();
