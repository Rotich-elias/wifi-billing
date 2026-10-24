<?php

namespace App\Services;

use App\Jobs\SendVoucherSms;
use App\Jobs\SyncVoucherToMikrotik;
use App\Models\Payment;
use App\Models\Voucher;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private string $consumerKey;

    private string $consumerSecret;

    private string $shortcode;

    private string $passkey;

    private string $callbackUrl;

    private string $baseUrl;

    public function __construct()
    {
        $this->consumerKey = (string) config('mpesa.consumer_key');
        $this->consumerSecret = (string) config('mpesa.consumer_secret');
        $this->shortcode = (string) config('mpesa.shortcode');
        $this->passkey = (string) config('mpesa.passkey');
        $this->callbackUrl = (string) config('mpesa.callback_url');
        $this->baseUrl = config('mpesa.environment') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    private function getAccessToken(): string
    {
        return Cache::remember('mpesa:access_token', now()->addMinutes(50), function () {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if (! $response->successful()) {
                throw new Exception('Failed to get M-Pesa access token (HTTP '.$response->status().').');
            }

            return (string) $response->json('access_token');
        });
    }

    public function initiateSTKPush(Voucher $voucher, string $phone): Payment
    {
        $phone = $this->formatPhone($phone);
        $amount = (int) ceil((float) $voucher->plan->price);

        if ($amount < 1) {
            throw new Exception('Invalid payment amount.');
        }

        // Persist the intent before calling M-Pesa. If the process dies after
        // Safaricom accepts the push, the callback still has a payment to find.
        $payment = Payment::create([
            'voucher_id' => $voucher->id,
            'phone' => $phone,
            'amount' => $amount,
            'status' => 'pending',
        ]);

        try {
            $timestamp = now()->format('YmdHis');
            $password = base64_encode($this->shortcode.$this->passkey.$timestamp);
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                    'BusinessShortCode' => $this->shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $this->shortcode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $this->callbackUrl,
                    'AccountReference' => $voucher->code,
                    'TransactionDesc' => "WiFi voucher: {$voucher->plan->name}",
                ]);

            Log::info('M-Pesa STK Push response', [
                'payment_id' => $payment->id,
                'http_status' => $response->status(),
                'checkout_request_id' => $response->json('CheckoutRequestID'),
            ]);

            if (! $response->successful()) {
                throw new Exception('STK Push failed (HTTP '.$response->status().').');
            }

            $data = $response->json();

            if (($data['ResponseCode'] ?? '') !== '0') {
                throw new Exception('STK Push error: '.($data['ResponseDescription'] ?? 'Unknown error'));
            }

            $payment->update([
                'merchant_request_id' => $data['MerchantRequestID'] ?? null,
                'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
            ]);

            return $payment;
        } catch (\Throwable $e) {
            $payment->update([
                'status' => 'failed',
                'result_desc' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function handleCallback(array $data): void
    {
        $stkCallback = $data['Body']['stkCallback'] ?? null;

        if (! is_array($stkCallback)) {
            return;
        }

        $payment = Payment::where('merchant_request_id', $stkCallback['MerchantRequestID'] ?? null)
            ->orWhere('checkout_request_id', $stkCallback['CheckoutRequestID'] ?? null)
            ->first();

        if (! $payment) {
            Log::warning('No payment found for M-Pesa callback', [
                'merchant_request_id' => $stkCallback['MerchantRequestID'] ?? null,
                'checkout_request_id' => $stkCallback['CheckoutRequestID'] ?? null,
            ]);

            return;
        }

        $resultCode = (int) ($stkCallback['ResultCode'] ?? -1);

        if ($resultCode === 0) {
            $metadata = collect($stkCallback['CallbackMetadata']['Item'] ?? [])
                ->pluck('Value', 'Name')
                ->all();

            $this->completePayment($payment, $metadata, $data, verifyAmount: true);

            return;
        }

        DB::transaction(function () use ($payment, $stkCallback, $resultCode, $data) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            // Never regress a terminal state.
            if (! $locked || $locked->status !== 'pending') {
                return;
            }

            $locked->update([
                'status' => 'failed',
                'result_code' => (string) $resultCode,
                'result_desc' => $stkCallback['ResultDesc'] ?? null,
                'raw_callback' => $data,
            ]);
        });
    }

    /**
     * Mark a pending payment completed and fulfil the voucher exactly once.
     */
    public function completePayment(
        Payment $payment,
        array $metadata = [],
        ?array $raw = null,
        bool $verifyAmount = false,
    ): bool {
        return DB::transaction(function () use ($payment, $metadata, $raw, $verifyAmount) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            $callbackAmount = $metadata['Amount'] ?? null;

            if ($verifyAmount && (
                $callbackAmount === null
                || (int) round((float) $callbackAmount) !== (int) round((float) $locked->amount)
            )) {
                Log::warning('M-Pesa amount mismatch', [
                    'payment_id' => $locked->id,
                    'expected' => (float) $locked->amount,
                    'received' => $callbackAmount,
                ]);

                $locked->update([
                    'status' => 'failed',
                    'result_desc' => 'Amount mismatch',
                    'raw_callback' => $raw,
                ]);

                return false;
            }

            $locked->update([
                'status' => 'completed',
                'result_code' => '0',
                'result_desc' => 'Success',
                'mpesa_receipt' => $metadata['MpesaReceiptNumber'] ?? null,
                'paid_at' => now(),
                'raw_callback' => $raw,
            ]);

            $voucher = $locked->voucher;

            if (! $voucher || $voucher->status !== 'unused') {
                return true;
            }

            if (! empty($metadata['PhoneNumber'])) {
                $voucher->customer_phone = $metadata['PhoneNumber'];
            }

            $voucher->activate();

            SyncVoucherToMikrotik::dispatch($voucher, 'add')->afterCommit();
            SendVoucherSms::dispatch($voucher)->afterCommit();

            return true;
        });
    }

    public function querySTKStatus(Payment $payment): array
    {
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $payment->checkout_request_id,
            ]);

        return $response->json() ?? [];
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '254')) {
            $phone = '254'.$phone;
        }

        return $phone;
    }
}
