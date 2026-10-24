<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncVoucherToMikrotik;
use App\Models\Plan;
use App\Models\Voucher;
use App\Services\VoucherService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

    public function index(Request $request)
    {
        $query = Voucher::with('plan')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', '%'.$request->search.'%')
                    ->orWhere('customer_phone', 'like', '%'.$request->search.'%')
                    ->orWhere('customer_name', 'like', '%'.$request->search.'%');
            });
        }

        $vouchers = $query->paginate(20)->withQueryString();
        $plans = Plan::where('is_active', true)->get();

        return view('admin.vouchers.index', compact('vouchers', 'plans'));
    }

    public function create()
    {
        $plans = Plan::where('is_active', true)->get();

        return view('admin.vouchers.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'quantity' => 'required|integer|min:1|max:500',
            'activate' => 'boolean',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $vouchers = $this->voucherService->generate(
            $plan,
            $request->quantity,
            $request->boolean('activate'),
        );

        if ($request->boolean('print_after')) {
            $batchId = $vouchers->first()->batch_id;

            return redirect()->route('admin.vouchers.printBatch', ['batch' => $batchId]);
        }

        return redirect()->route('admin.vouchers.index')
            ->with('success', "{$vouchers->count()} voucher(s) generated for plan '{$plan->name}'.");
    }

    public function show(Voucher $voucher)
    {
        $voucher->load('plan', 'payments', 'syncLogs');

        return view('admin.vouchers.show', compact('voucher'));
    }

    public function edit(Voucher $voucher)
    {
        return view('admin.vouchers.edit', compact('voucher'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $request->validate([
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $voucher->update($request->only('customer_name', 'customer_phone'));

        return redirect()->route('admin.vouchers.show', $voucher)
            ->with('success', 'Voucher updated.');
    }

    public function destroy(Voucher $voucher)
    {
        $this->voucherService->revokeVoucher($voucher);

        return redirect()->route('admin.vouchers.index')
            ->with('success', 'Voucher revoked.');
    }

    public function syncMikrotik(Voucher $voucher)
    {
        $action = $voucher->mikrotik_synced ? 'remove' : 'add';

        // Never put an unpaid or expired voucher on the router: that would
        // grant free access to anyone holding the printed code.
        if ($action === 'add' && $voucher->status !== 'active') {
            return back()->with('error', 'Only active vouchers can be provisioned.');
        }

        SyncVoucherToMikrotik::dispatch($voucher, $action);

        return back()->with('success', "MikroTik {$action} queued for voucher {$voucher->code}.");
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:revoke,delete,sync,print',
            'voucher_ids' => 'required|array|max:500',
            'voucher_ids.*' => 'integer|exists:vouchers,id',
        ]);

        $vouchers = Voucher::with('plan')->whereIn('id', $request->voucher_ids)->get();

        if ($request->action === 'print') {
            $pdf = Pdf::loadView('pdf.vouchers', compact('vouchers'))
                ->setPaper('a4', 'portrait');

            return $pdf->download('vouchers-selected.pdf');
        }

        $applied = 0;

        foreach ($vouchers as $voucher) {
            if ($request->action === 'sync' && $voucher->status !== 'active') {
                continue;
            }

            match ($request->action) {
                'revoke' => $this->voucherService->revokeVoucher($voucher),
                'delete' => $this->deleteVoucher($voucher),
                'sync' => SyncVoucherToMikrotik::dispatch($voucher, 'add'),
            };

            $applied++;
        }

        return back()->with('success', "Bulk {$request->action} applied to {$applied} voucher(s).");
    }

    private function deleteVoucher(Voucher $voucher): void
    {
        // Remove router access first, then soft-delete so payment history survives.
        $this->voucherService->revokeVoucher($voucher);
        $voucher->delete();
    }

    public function printSingle(Voucher $voucher)
    {
        $voucher->load('plan');
        $vouchers = collect([$voucher]);

        $pdf = Pdf::loadView('pdf.vouchers', compact('vouchers'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("voucher-{$voucher->code}.pdf");
    }

    public function printBatch(Request $request)
    {
        $batchId = $request->query('batch');
        $vouchers = Voucher::with('plan')
            ->where('batch_id', $batchId)
            ->get();

        abort_if($vouchers->isEmpty(), 404);

        $pdf = Pdf::loadView('pdf.vouchers', compact('vouchers'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('vouchers-batch.pdf');
    }

    public function printFiltered(Request $request)
    {
        $query = Voucher::with('plan');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        $vouchers = $query->latest()->limit(200)->get();

        $pdf = Pdf::loadView('pdf.vouchers', compact('vouchers'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('vouchers-filtered.pdf');
    }
}
