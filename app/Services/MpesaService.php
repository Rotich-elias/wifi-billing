<?php

namespace App\Services;

use App\Jobs\SendVoucherSms;
use App\Jobs\SyncVoucherToMikrotik;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Voucher;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    /**
     * How long a pending payment may stay unresolved before recovery expires it.
     */
    private const PENDING_GRACE_MINUTES = 5;

    /**
     * Very old pending rows are expired without calling Daraja. This stops a
     * handful of stuck rows from being queried forever (and hitting rate limits).
     */
    private const PENDING_MAX_QUERY_MINUTES = 60;

    private string $consumerKey;

    private string $consumerSecret;

    private string $shortcode;

    private string $passkey;

    private string $baseUrl;

    private string $callbackBaseUrl;

    private string $callbackSecret;

    public function __construct()
    {
        $this->consumerKey = (string) config('mpesa.consumer_key');
        $this->consumerSecret = (string) config('mpesa.consumer_secret');
        $this->shortcode = (string) config('mpesa.shortcode');
        $this->passkey = (string) config('mpesa.passkey');
        $this->callbackBaseUrl = rtrim((string) config('mpesa.callback_base_url'), '/');
        $this->callbackSecret = (string) config('mpesa.callback_secret');
        $this->baseUrl = config('mpesa.env') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Create the payment first, then ask Safaricom to push the STK prompt.
     *
     * @param  array{name?: string|null, mac?: string|null, ip?: string|null, login_url?: string|null, orig_url?: string|null}  $context
     */
    public function initiatePayment(Plan $plan, string $phone, array $context = []): Payment
    {
        $phone = Payment::normalisePhone($phone);
        $amount = (int) ceil((float) $plan->price);

        if ($amount < 1) {
            throw new Exception('Invalid payment amount.');
        }

        if ($this->callbackBaseUrl === '' || $this->callbackSecret === '') {
            throw new Exception('M-Pesa callback base URL and secret are not configured.');
        }

        $payment = Payment::create([
            'plan_id' => $plan->id,
            'customer_name' => $context['name'] ?? null,
            'phone' => $phone,
            'amount' => $amount,
            'status' => Payment::STATUS_PENDING,
            'mac' => $context['mac'] ?? null,
            'ip' => $context['ip'] ?? null,
            'login_url' => $context['login_url'] ?? null,
            'orig_url' => $context['orig_url'] ?? null,
        ]);

        try {
            $this->sendStkPush($payment);

            return $payment->fresh();
        } catch (\Throwable $e) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'result_desc' => $e->getMessage(),
            ]);

            Log::error('M-Pesa STK Push failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendStkPush(Payment $payment): void
    {
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $payment->amount,
                'PartyA' => $payment->phone,
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $payment->phone,
                'CallBackURL' => $this->callbackUrl(),
                'AccountReference' => 'WIFI'.$payment->id,
                'TransactionDesc' => 'WiFi access',
            ]);

        Log::info('M-Pesa STK Push response', [
            'payment_id' => $payment->id,
            'http_status' => $response->status(),
            'response_code' => $response->json('ResponseCode'),
            'checkout_request_id' => $response->json('CheckoutRequestID'),
        ]);

        if (! $response->successful()) {
            throw new Exception('STK Push failed (HTTP '.$response->status().').');
        }

        $data = $response->json();

        if (($data['ResponseCode'] ?? '') !== '0') {
            throw new Exception('STK Push error: '.($data['ResponseDescription'] ?? 'Unknown error'));
        }

        if (empty($data['CheckoutRequestID'])) {
            throw new Exception('STK Push response did not include a CheckoutRequestID.');
        }

        $payment->update([
            'merchant_request_id' => $data['MerchantRequestID'] ?? null,
            'checkout_request_id' => $data['CheckoutRequestID'],
        ]);
    }

    public function callbackUrl(): string
    {
        return $this->callbackBaseUrl.'/api/mpesa/callback/'.rawurlencode($this->callbackSecret);
    }

    private function getAccessToken(): string
    {
        $cacheKey = 'mpesa:access_token:'.md5($this->baseUrl.$this->consumerKey);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if (! $response->successful()) {
                throw new Exception('Failed to get M-Pesa access token (HTTP '.$response->status().').');
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * Process a Daraja callback. Unknown or already-processed callbacks are
     * ignored; the controller always returns 200.
     */
    public function handleCallback(array $data): void
    {
        $stkCallback = $data['Body']['stkCallback'] ?? null;

        if (! is_array($stkCallback)) {
            return;
        }

        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;

        if (empty($checkoutRequestId)) {
            return;
        }

        $payment = Payment::where('checkout_request_id', $checkoutRequestId)->first();

        if (! $payment) {
            Log::warning('No payment found for M-Pesa callback', [
                'checkout_request_id' => $checkoutRequestId,
                'merchant_request_id' => $stkCallback['MerchantRequestID'] ?? null,
            ]);

            return;
        }

        $resultCode = (int) ($stkCallback['ResultCode'] ?? -1);

        if ($resultCode !== 0) {
            $this->markFailed($payment, $stkCallback, $data);

            return;
        }

        $metadata = collect($stkCallback['CallbackMetadata']['Item'] ?? [])
            ->pluck('Value', 'Name')
            ->all();

        $voucher = $this->completePayment($payment, $metadata, $data, verifyAmount: true);

        if ($voucher) {
            $this->queueFulfilment($voucher);
        }
    }

    /**
     * Mark a pending payment paid and create its voucher exactly once.
     */
    public function completePayment(
        Payment $payment,
        array $metadata = [],
        ?array $raw = null,
        bool $verifyAmount = true,
    ): ?Voucher {
        try {
            $voucher = DB::transaction(function () use ($payment, $metadata, $raw, $verifyAmount) {
                $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

                if (! $locked || $locked->status !== Payment::STATUS_PENDING) {
                    return null;
                }

                if ($verifyAmount) {
                    $received = $metadata['Amount'] ?? null;
                    $expected = (float) $locked->amount;

                    if ($received === null || round((float) $received, 2) !== round($expected, 2)) {
                        Log::warning('M-Pesa amount mismatch', [
                            'payment_id' => $locked->id,
                            'expected' => $expected,
                            'received' => $received,
                        ]);

                        $locked->update([
                            'status' => Payment::STATUS_FAILED,
                            'result_code' => 'AMOUNT_MISMATCH',
                            'result_desc' => 'Amount mismatch',
                            'raw_callback' => $raw,
                        ]);

                        return null;
                    }
                }

                $receipt = $metadata['MpesaReceiptNumber'] ?? null;

                // A success without a receipt is not enough evidence to grant
                // access. Leave the payment pending so recovery can retry.
                if (empty($receipt)) {
                    Log::warning('M-Pesa success response without a receipt', [
                        'payment_id' => $locked->id,
                    ]);

                    return null;
                }

                if (Payment::where('mpesa_receipt', $receipt)
                    ->whereKeyNot($locked->id)
                    ->exists()) {
                    Log::alert('Duplicate M-Pesa receipt in callback', [
                        'payment_id' => $locked->id,
                        'receipt' => $receipt,
                    ]);

                    $locked->update([
                        'status' => Payment::STATUS_FAILED,
                        'result_code' => 'DUPLICATE_RECEIPT',
                        'result_desc' => 'Duplicate M-Pesa receipt',
                        'raw_callback' => $raw,
                    ]);

                    return null;
                }

                $locked->update([
                    'status' => Payment::STATUS_PAID,
                    'result_code' => '0',
                    'result_desc' => 'Success',
                    'mpesa_receipt' => $receipt,
                    'paid_at' => now(),
                    'raw_callback' => $raw,
                ]);

                $plan = $locked->plan ?? Plan::find($locked->plan_id);

                if (! $plan) {
                    throw new Exception('Payment has no plan; cannot create a voucher.');
                }

                $voucher = $locked->voucher;

                if (! $voucher) {
                    $voucher = Voucher::create([
                        'plan_id' => $plan->id,
                        'customer_name' => $locked->customer_name,
                        'customer_phone' => $this->normaliseCallbackPhone($metadata['PhoneNumber'] ?? null)
                            ?: $locked->phone,
                        'status' => 'unused',
                    ]);
                }

                if ($voucher->status === 'unused') {
                    $voucher->activate();
                }

                $locked->update(['voucher_id' => $voucher->id]);

                return $voucher;
            });
        } catch (QueryException $e) {
            // A concurrent callback may have stored the same receipt first.
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                Log::alert('Duplicate M-Pesa receipt rejected', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
                $this->markFailed($payment, [
                    'ResultCode' => 'DUPLICATE_RECEIPT',
                    'ResultDesc' => 'Duplicate M-Pesa receipt',
                ], $raw);

                return null;
            }

            throw $e;
        }

        return $voucher;
    }

    private function queueFulfilment(Voucher $voucher): void
    {
        try {
            SyncVoucherToMikrotik::dispatch($voucher, 'add');
        } catch (\Throwable $e) {
            // The payment is already paid. Keep the voucher visible as
            // "Activating..." and let an admin retry provisioning.
            Log::error('Could not queue voucher provisioning', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (config('sms.enabled')) {
            try {
                SendVoucherSms::dispatch($voucher);
            } catch (\Throwable $e) {
                Log::error('Could not queue voucher SMS', [
                    'voucher_id' => $voucher->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function markFailed(Payment $payment, array $stkCallback, ?array $raw = null): void
    {
        DB::transaction(function () use ($payment, $stkCallback, $raw) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== Payment::STATUS_PENDING) {
                return;
            }

            $locked->update([
                'status' => Payment::STATUS_FAILED,
                'result_code' => (string) ($stkCallback['ResultCode'] ?? ''),
                'result_desc' => $stkCallback['ResultDesc'] ?? null,
                'raw_callback' => $raw,
            ]);
        });
    }

    /**
     * Query Daraja for a pending STK request and reconcile the payment.
     */
    public function reconcilePendingPayment(Payment $payment): void
    {
        if (! $payment->isPending()) {
            return;
        }

        // Expire abandoned rows without asking Daraja. These can never be paid
        // and would otherwise be queried on every scheduler run indefinitely.
        if ($payment->created_at->lt(now()->subMinutes(self::PENDING_MAX_QUERY_MINUTES))) {
            $this->expirePending($payment, 'Payment expired before M-Pesa confirmation could be checked');

            return;
        }

        if (! $payment->checkout_request_id) {
            if ($payment->created_at->lt(now()->subMinutes(self::PENDING_GRACE_MINUTES))) {
                $this->expirePending($payment, 'No STK request was created');
            }

            return;
        }

        try {
            $result = $this->querySTKStatus($payment);
        } catch (\Throwable $e) {
            Log::warning('STK query failed during recovery', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            // Keep retrying briefly, then stop. A Daraja outage must not leave
            // a payment pending forever.
            if ($payment->created_at->lt(now()->subMinutes(self::PENDING_GRACE_MINUTES))) {
                $this->expirePending(
                    $payment,
                    'M-Pesa status could not be confirmed before timeout: '.$e->getMessage(),
                );
            }

            return;
        }

        $resultCode = $result['ResultCode'] ?? null;

        if ((string) $resultCode === '0') {
            $voucher = $this->completePayment($payment, [
                'MpesaReceiptNumber' => $result['MpesaReceiptNumber'] ?? null,
            ], raw: $result, verifyAmount: false);

            if ($voucher) {
                $this->queueFulfilment($voucher);
            }

            return;
        }

        if ($resultCode !== null && in_array((string) $resultCode, ['1', '1032', '1037', '2001'], true)) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'result_code' => (string) $resultCode,
                'result_desc' => $result['ResultDesc'] ?? null,
                'raw_callback' => $result,
            ]);

            return;
        }

        if ($payment->created_at->lt(now()->subMinutes(self::PENDING_GRACE_MINUTES))) {
            $this->expirePending(
                $payment,
                $result['ResultDesc'] ?? 'Timed out',
                $resultCode === null ? null : (string) $resultCode,
                $result,
            );
        }
    }

    /**
     * Expire a payment only if it is still pending. This guards against a
     * callback that completes the payment while recovery is running.
     */
    private function expirePending(Payment $payment, string $description, ?string $resultCode = null, ?array $raw = null): void
    {
        DB::transaction(function () use ($payment, $description, $resultCode, $raw) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== Payment::STATUS_PENDING) {
                return;
            }

            $locked->update([
                'status' => Payment::STATUS_EXPIRED,
                'result_code' => $resultCode,
                'result_desc' => $description,
                'raw_callback' => $raw,
            ]);
        });

        $payment->refresh();
    }

    public function querySTKStatus(Payment $payment): array
    {
        if (! $payment->checkout_request_id) {
            throw new Exception('Payment has no CheckoutRequestID.');
        }

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

        Log::info('M-Pesa STK query response', [
            'payment_id' => $payment->id,
            'http_status' => $response->status(),
            'result_code' => $response->json('ResultCode'),
        ]);

        if (! $response->successful()) {
            throw new Exception('STK query failed (HTTP '.$response->status().').');
        }

        return $response->json() ?? [];
    }

    private function normaliseCallbackPhone(mixed $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalised = Payment::normalisePhone((string) $phone);

        return preg_match('/^254(7|1)\d{8}$/', $normalised) ? $normalised : null;
    }
}
