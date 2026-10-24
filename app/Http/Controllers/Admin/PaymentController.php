<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\MpesaService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private MpesaService $mpesa) {}

    public function index(Request $request)
    {
        $query = Payment::with('voucher.plan')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('phone', 'like', '%'.$request->search.'%')
                    ->orWhere('mpesa_receipt', 'like', '%'.$request->search.'%');
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
            'total' => Payment::where('status', 'completed')->sum('amount'),
            'today' => Payment::where('status', 'completed')->whereDate('paid_at', today())->sum('amount'),
            'pending' => Payment::where('status', 'pending')->count(),
            'failed' => Payment::where('status', 'failed')->count(),
        ];

        return view('admin.payments.index', compact('payments', 'summary'));
    }

    public function show(Payment $payment)
    {
        $payment->load('voucher.plan');

        return view('admin.payments.show', compact('payment'));
    }

    public function queryStatus(Payment $payment)
    {
        $result = $this->mpesa->querySTKStatus($payment);

        $responseCode = $result['ResultCode'] ?? null;

        if ($responseCode === 0 || $responseCode === '0') {
            $completed = $this->mpesa->completePayment($payment, [
                'MpesaReceiptNumber' => $result['MpesaReceiptNumber'] ?? null,
            ], raw: $result);

            return back()->with('info', $completed
                ? 'Status checked: payment completed and voucher activated.'
                : 'Status checked: payment was already processed.');
        }

        if ($payment->status === 'pending' && in_array((string) $responseCode, ['1032', '1037'], true)) {
            $payment->update([
                'status' => 'failed',
                'result_code' => (string) $responseCode,
                'result_desc' => $result['ResultDesc'] ?? null,
            ]);
        }

        return back()->with('info', 'Status checked: '.($result['ResultDesc'] ?? 'No response'));
    }
}
