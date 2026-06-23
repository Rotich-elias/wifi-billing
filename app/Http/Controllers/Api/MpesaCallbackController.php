<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MpesaCallbackController extends Controller
{
    public function __construct(private MpesaService $mpesa) {}

    public function handle(Request $request): JsonResponse
    {
        $this->mpesa->handleCallback($request->all());
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
