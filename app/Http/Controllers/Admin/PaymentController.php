<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncVoucherToMikrotik;
use App\Models\Payment;
use App\Services\MpesaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private MpesaService $mpesa) {}

    public function index(Request $request)
    {
        $query = Payment::with('plan', 'voucher.plan')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('phone', 'like', '%'.$request->search.'%')
                    ->orWhere('mpesa_receipt', 'like', '%'.$request->search.'%')
                    ->orWhere('checkout_request_id', 'like', '%'.$request->search.'%');
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate(20)->withQueryString();

        $summary = [
            'total' => Payment::where('status', Payment::STATUS_PAID)->sum('amount'),
            'today' => Payment::where('status', Payment::STATUS_PAID)->whereDate('paid_at', today())->sum('amount'),
            'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
            'failed' => Payment::where('status', Payment::STATUS_FAILED)->count(),
        ];

        return view('admin.payments.index', compact('payments', 'summary'));
    }

    public function show(Payment $payment)
    {
        $payment->load('plan', 'voucher.plan');

        return view('admin.payments.show', compact('payment'));
    }

    public function queryStatus(Payment $payment): RedirectResponse
    {
        try {
            $this->mpesa->reconcilePendingPayment($payment);
        } catch (\Throwable $e) {
            return back()->with('error', 'Status check failed: '.$e->getMessage());
        }

        $payment->refresh();

        return back()->with('info', match ($payment->status) {
            Payment::STATUS_PAID => 'Payment confirmed and voucher created.',
            Payment::STATUS_FAILED => 'Payment failed: '.($payment->result_desc ?? 'no reason given'),
            Payment::STATUS_EXPIRED => 'Payment expired.',
            default => 'Payment is still pending.',
        });
    }

    public function retryProvisioning(Payment $payment): RedirectResponse
    {
        $voucher = $payment->voucher;

        if (! $voucher) {
            return back()->with('error', 'This payment has no voucher yet.');
        }

        SyncVoucherToMikrotik::dispatch($voucher, 'add');

        return back()->with('success', "Provisioning queued for voucher {$voucher->code}.");
    }
}
