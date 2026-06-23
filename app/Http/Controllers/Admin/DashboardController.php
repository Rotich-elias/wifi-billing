<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Voucher;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_vouchers'   => Voucher::count(),
            'unused_vouchers'  => Voucher::where('status', 'unused')->count(),
            'active_vouchers'  => Voucher::where('status', 'active')->count(),
            'expired_vouchers' => Voucher::where('status', 'expired')->count(),
            'total_revenue'    => Payment::where('status', 'completed')->sum('amount'),
            'today_revenue'    => Payment::where('status', 'completed')
                ->whereDate('paid_at', today())->sum('amount'),
            'total_plans'      => Plan::where('is_active', true)->count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
        ];

        $recentPayments = Payment::with('voucher.plan')
            ->where('status', 'completed')
            ->latest('paid_at')
            ->limit(8)
            ->get();

        $recentVouchers = Voucher::with('plan')
            ->latest()
            ->limit(8)
            ->get();

        $revenueByPlan = Payment::join('vouchers', 'payments.voucher_id', '=', 'vouchers.id')
            ->join('plans', 'vouchers.plan_id', '=', 'plans.id')
            ->where('payments.status', 'completed')
            ->selectRaw('plans.name, plans.type, SUM(payments.amount) as total, COUNT(*) as count')
            ->groupBy('plans.id', 'plans.name', 'plans.type')
            ->get();

        return view('admin.dashboard', compact('stats', 'recentPayments', 'recentVouchers', 'revenueByPlan'));
    }
}
