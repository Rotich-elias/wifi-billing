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
            'total_vouchers' => Voucher::count(),
            'unused_vouchers' => Voucher::where('status', 'unused')->count(),
            'active_vouchers' => Voucher::where('status', 'active')->count(),
            'expired_vouchers' => Voucher::where('status', 'expired')->count(),
            'total_revenue' => Payment::where('status', Payment::STATUS_PAID)->sum('amount'),
            'today_revenue' => Payment::where('status', Payment::STATUS_PAID)
                ->whereDate('paid_at', today())->sum('amount'),
            'total_plans' => Plan::where('is_active', true)->count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
        ];

        $recentPayments = Payment::with('plan', 'voucher.plan')
            ->where('status', Payment::STATUS_PAID)
            ->latest('paid_at')
            ->limit(8)
            ->get();

        $recentVouchers = Voucher::with('plan')
            ->latest()
            ->limit(8)
            ->get();

        $revenueByPlan = Payment::join('plans', 'payments.plan_id', '=', 'plans.id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->selectRaw('plans.name, plans.type, SUM(payments.amount) as total, COUNT(*) as count')
            ->groupBy('plans.id', 'plans.name', 'plans.type')
            ->get();

        return view('admin.dashboard', compact('stats', 'recentPayments', 'recentVouchers', 'revenueByPlan'));
    }
}
