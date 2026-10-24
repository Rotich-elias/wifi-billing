<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Voucher;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoucherPortalController extends Controller
{
    public function __construct(private MpesaService $mpesa) {}

    public function index()
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();

        return view('portal.index', compact('plans'));
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'phone' => ['required', 'regex:/^(07|01|\+?254)\d{8}$/'],
            'name' => 'nullable|string|max:100',
        ]);

        // Only sell plans that are currently offered.
        $plan = Plan::where('is_active', true)->findOrFail($request->plan_id);

        $voucher = Voucher::create([
            'plan_id' => $plan->id,
            'customer_name' => $request->name,
            'customer_phone' => $request->phone,
        ]);

        try {
            $payment = $this->mpesa->initiateSTKPush($voucher, $request->phone);

            return view('portal.pending', compact('voucher', 'payment'));
        } catch (\Throwable $e) {
            Log::error('M-Pesa initiation failed', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
            ]);

            // Keep the voucher/payment audit trail instead of deleting it;
            // the payment is already marked failed by the service.
            return back()->withErrors([
                'phone' => 'Payment initiation failed. Please try again.',
            ]);
        }
    }

    public function status(Voucher $voucher)
    {
        $voucher->checkAndExpire();
        $voucher->load('plan', 'payment');

        return view('portal.status', compact('voucher'));
    }

    public function check(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);

        $voucher = Voucher::where('code', strtoupper(trim($request->code)))->first();

        if (! $voucher) {
            // Same response for unknown/invalid codes to avoid an existence oracle.
            return back()->withErrors(['code' => 'Voucher code not found.']);
        }

        return redirect()->route('portal.status', ['voucher' => $voucher->access_token]);
    }
}
