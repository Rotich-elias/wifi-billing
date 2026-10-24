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

        // Log every callback for audit. The secret is in the path, never in
        // this payload, so there are no credentials to redact here.
        Log::info('M-Pesa callback received', [
            'ip' => $request->ip(),
            'payload' => $payload,
        ]);

        try {
            $this->mpesa->handleCallback($payload);
        } catch (Throwable $e) {
            Log::error('M-Pesa callback processing failed', [
                'checkout_request_id' => $payload['Body']['stkCallback']['CheckoutRequestID'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // Always acknowledge: Safaricom retries non-2xx responses, and the
        // recovery job will reconcile anything we could not process now.
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }
}
