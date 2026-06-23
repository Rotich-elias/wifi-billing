<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Voucher;
use App\Services\MpesaService;
use Illuminate\Http\Request;

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
            'phone'   => ['required', 'regex:/^(07|01|\+?254)\d{8}$/'],
            'name'    => 'nullable|string|max:100',
        ]);

        $plan    = Plan::findOrFail($request->plan_id);
        $voucher = Voucher::create([
            'plan_id'       => $plan->id,
            'customer_name' => $request->name,
            'customer_phone'=> $request->phone,
        ]);

        try {
            $payment = $this->mpesa->initiateSTKPush($voucher, $request->phone);
            return view('portal.pending', compact('voucher', 'payment'));
        } catch (\Exception $e) {
            $voucher->delete();
            return back()->withErrors(['phone' => 'Payment initiation failed: '.$e->getMessage()]);
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
        $request->validate(['code' => 'required|string']);
        $voucher = Voucher::where('code', strtoupper($request->code))->firstOrFail();
        return redirect()->route('portal.status', $voucher);
    }
}
