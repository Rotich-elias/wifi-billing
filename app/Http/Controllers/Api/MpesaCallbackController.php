<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MpesaCallbackController extends Controller
{
    public function __construct(private MpesaService $mpesa) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $stkCallback = $payload['Body']['stkCallback'] ?? null;

        if (
            ! is_array($stkCallback)
            || empty($stkCallback['MerchantRequestID'])
            || empty($stkCallback['CheckoutRequestID'])
            || ! array_key_exists('ResultCode', $stkCallback)
        ) {
            Log::warning('Rejected malformed M-Pesa callback', ['ip' => $request->ip()]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Invalid payload',
            ], 422);
        }

        try {
            $this->mpesa->handleCallback($payload);
        } catch (Throwable $e) {
            Log::error('M-Pesa callback processing failed', [
                'checkout_request_id' => $stkCallback['CheckoutRequestID'],
                'error' => $e->getMessage(),
            ]);

            // Non-2xx tells Safaricom to retry.
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Processing failed',
            ], 500);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
