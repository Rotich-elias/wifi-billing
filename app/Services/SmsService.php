<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use App\Models\Voucher;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private mixed $sms;

    public function __construct()
    {
        if (! config('sms.enabled')) {
            return;
        }

        $at = new AfricasTalking(config('africastalking.username'), config('africastalking.api_key'));
        $this->sms = $at->sms();
    }

    public function sendVoucherCode(Voucher $voucher): bool
    {
        if (! config('sms.enabled') || ! isset($this->sms)) {
            return false;
        }

        $phone = $voucher->customer_phone;
        if (! $phone) {
            return false;
        }

        $plan = $voucher->plan;
        $business = config('app.business_name', config('app.name'));
        $message = implode("\n", [
            "{$business}",
            'Your WiFi voucher is ready!',
            "Code: {$voucher->code}",
            "Plan: {$plan->name} ({$plan->duration_label})",
            'Login at the WiFi portal and enter your code.',
            "Username & Password: {$voucher->code}",
        ]);

        try {
            $result = $this->sms->send([
                'to' => $this->formatPhone($phone),
                'message' => $message,
                'from' => config('africastalking.sender_id') ?: null,
            ]);

            Log::info('SMS sent', ['phone' => $this->maskPhone($phone), 'result' => $result]);

            return true;
        } catch (\Exception $e) {
            Log::error('SMS failed', ['phone' => $this->maskPhone($phone), 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function sendExpiryWarning(Voucher $voucher): bool
    {
        if (! config('sms.enabled') || ! isset($this->sms)) {
            return false;
        }

        $phone = $voucher->customer_phone;
        if (! $phone) {
            return false;
        }

        $remaining = $voucher->expires_at?->diffForHumans();
        $message = implode("\n", [
            config('app.business_name', config('app.name')),
            "Your WiFi voucher ({$voucher->code}) expires {$remaining}.",
            'Buy a new voucher to stay connected.',
        ]);

        try {
            $this->sms->send([
                'to' => $this->formatPhone($phone),
                'message' => $message,
                'from' => config('africastalking.sender_id') ?: null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('SMS expiry warning failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }
        if (! str_starts_with($phone, '254') && ! str_starts_with($phone, '+')) {
            $phone = '254'.$phone;
        }

        return '+'.ltrim($phone, '+');
    }

    private function maskPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (strlen($phone) < 6) {
            return '***';
        }

        return substr($phone, 0, 4).'****'.substr($phone, -2);
    }
}
