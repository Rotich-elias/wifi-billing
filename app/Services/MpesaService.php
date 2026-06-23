<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Voucher;
use Exception;
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
        $this->consumerKey    = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');
        $this->shortcode      = config('mpesa.shortcode');
        $this->passkey        = config('mpesa.passkey');
        $this->callbackUrl    = config('mpesa.callback_url');
        $this->baseUrl        = config('mpesa.environment') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

        if (!$response->successful()) {
            throw new Exception('Failed to get M-Pesa access token: '.$response->body());
        }

        return $response->json('access_token');
    }

    public function initiateSTKPush(Voucher $voucher, string $phone): Payment
    {
        $phone     = $this->formatPhone($phone);
        $amount    = (int) ceil($voucher->plan->price);
        $timestamp = now()->format('YmdHis');
        $password  = base64_encode($this->shortcode.$this->passkey.$timestamp);

        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                'BusinessShortCode' => $this->shortcode,
                'Password'          => $password,
                'Timestamp'         => $timestamp,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => $amount,
                'PartyA'            => $phone,
                'PartyB'            => $this->shortcode,
                'PhoneNumber'       => $phone,
                'CallBackURL'       => $this->callbackUrl,
                'AccountReference'  => $voucher->code,
                'TransactionDesc'   => "WiFi voucher: {$voucher->plan->name}",
            ]);

        Log::info('M-Pesa STK Push response', $response->json() ?? []);

        if (!$response->successful()) {
            throw new Exception('STK Push failed: '.$response->body());
        }

        $data = $response->json();

        if (($data['ResponseCode'] ?? '') !== '0') {
            throw new Exception('STK Push error: '.($data['ResponseDescription'] ?? 'Unknown error'));
        }

        return Payment::create([
            'voucher_id'          => $voucher->id,
            'phone'               => $phone,
            'amount'              => $voucher->plan->price,
            'status'              => 'pending',
            'merchant_request_id' => $data['MerchantRequestID'],
            'checkout_request_id' => $data['CheckoutRequestID'],
        ]);
    }

    public function handleCallback(array $data): void
    {
        Log::info('M-Pesa callback received', $data);

        $stkCallback = $data['Body']['stkCallback'] ?? null;
        if (!$stkCallback) {
            return;
        }

        $payment = Payment::where('merchant_request_id', $stkCallback['MerchantRequestID'])
            ->orWhere('checkout_request_id', $stkCallback['CheckoutRequestID'])
            ->first();

        if (!$payment) {
            Log::warning('No payment found for callback', $stkCallback);
            return;
        }

        $resultCode = $stkCallback['ResultCode'];

        if ($resultCode === 0) {
            $meta = collect($stkCallback['CallbackMetadata']['Item'] ?? [])
                ->pluck('Value', 'Name');

            $payment->update([
                'status'       => 'completed',
                'result_code'  => $resultCode,
                'result_desc'  => $stkCallback['ResultDesc'],
                'mpesa_receipt'=> $meta['MpesaReceiptNumber'] ?? null,
                'paid_at'      => now(),
                'raw_callback' => $data,
            ]);

            $voucher = $payment->voucher;
            $voucher->customer_phone = $meta['PhoneNumber'] ?? $payment->phone;
            $voucher->save();

            app(MikrotikService::class)->syncVoucher($voucher, 'add');
        } else {
            $payment->update([
                'status'      => 'failed',
                'result_code' => $resultCode,
                'result_desc' => $stkCallback['ResultDesc'],
                'raw_callback'=> $data,
            ]);
        }
    }

    public function querySTKStatus(Payment $payment): array
    {
        $timestamp = now()->format('YmdHis');
        $password  = base64_encode($this->shortcode.$this->passkey.$timestamp);
        $token     = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                'BusinessShortCode' => $this->shortcode,
                'Password'          => $password,
                'Timestamp'         => $timestamp,
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
        if (!str_starts_with($phone, '254')) {
            $phone = '254'.$phone;
        }
        return $phone;
    }
}
