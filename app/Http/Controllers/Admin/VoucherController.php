<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Voucher;
use App\Services\MikrotikService;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(
        private VoucherService $voucherService,
        private MikrotikService $mikrotikService,
    ) {}

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
            $query->where('code', 'like', '%'.$request->search.'%')
                ->orWhere('customer_phone', 'like', '%'.$request->search.'%')
                ->orWhere('customer_name', 'like', '%'.$request->search.'%');
        }

        $vouchers = $query->paginate(20)->withQueryString();
        $plans    = Plan::where('is_active', true)->get();

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
            'plan_id'  => 'required|exists:plans,id',
            'quantity' => 'required|integer|min:1|max:500',
        ]);

        $plan     = Plan::findOrFail($request->plan_id);
        $vouchers = $this->voucherService->generate($plan, $request->quantity);

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
            'customer_name'  => 'nullable|string|max:100',
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
        $action  = $voucher->mikrotik_synced ? 'remove' : 'add';
        $success = $this->mikrotikService->syncVoucher($voucher, $action);

        return back()->with(
            $success ? 'success' : 'error',
            $success ? "Voucher {$action}ed on MikroTik." : 'MikroTik sync failed. Check sync logs.'
        );
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action'     => 'required|in:revoke,delete,sync',
            'voucher_ids'=> 'required|array',
        ]);

        $vouchers = Voucher::whereIn('id', $request->voucher_ids)->get();

        foreach ($vouchers as $voucher) {
            match($request->action) {
                'revoke' => $this->voucherService->revokeVoucher($voucher),
                'delete' => $voucher->delete(),
                'sync'   => $this->mikrotikService->syncVoucher($voucher, 'add'),
            };
        }

        return back()->with('success', "Bulk {$request->action} applied to {$vouchers->count()} voucher(s).");
    }
}
