@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card primary h-100">
            <div class="card-body">
                <div class="text-muted small">Total Vouchers</div>
                <div class="fs-3 fw-bold">{{ number_format($stats['total_vouchers']) }}</div>
                <div class="text-muted small">
                    <span class="text-success">{{ $stats['active_vouchers'] }} active</span> &bull;
                    <span class="text-secondary">{{ $stats['unused_vouchers'] }} unused</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <div class="text-muted small">Total Revenue</div>
                <div class="fs-3 fw-bold">KES {{ number_format($stats['total_revenue'], 2) }}</div>
                <div class="text-muted small">Today: KES {{ number_format($stats['today_revenue'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <div class="text-muted small">Pending Payments</div>
                <div class="fs-3 fw-bold">{{ $stats['pending_payments'] }}</div>
                <div class="text-muted small">Awaiting STK confirmation</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card danger h-100">
            <div class="card-body">
                <div class="text-muted small">Expired Vouchers</div>
                <div class="fs-3 fw-bold">{{ $stats['expired_vouchers'] }}</div>
                <div class="text-muted small">{{ $stats['total_plans'] }} active plans</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="mb-0 fw-semibold">Recent Payments</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Phone</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayments as $payment)
                        <tr>
                            <td>{{ $payment->phone }}</td>
                            <td><span class="badge bg-primary-subtle text-primary">{{ $payment->voucher->plan->name ?? '—' }}</span></td>
                            <td class="fw-semibold">KES {{ number_format($payment->amount, 2) }}</td>
                            <td class="font-monospace small text-muted">{{ $payment->mpesa_receipt ?? '—' }}</td>
                            <td class="text-muted small">{{ $payment->paid_at?->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No payments yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Revenue by Plan</h6>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr><th>Plan</th><th>Sales</th><th>Revenue</th></tr>
                    </thead>
                    <tbody>
                        @forelse($revenueByPlan as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->count }}</td>
                            <td class="fw-semibold">KES {{ number_format($row->total, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Recent Vouchers</h6>
                <a href="{{ route('admin.vouchers.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> Generate
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr><th>Code</th><th>Plan</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentVouchers as $voucher)
                        <tr>
                            <td class="font-monospace fw-semibold">
                                <a href="{{ route('admin.vouchers.show', $voucher) }}" class="text-decoration-none">{{ $voucher->code }}</a>
                            </td>
                            <td class="small">{{ $voucher->plan->name }}</td>
                            <td><span class="badge bg-{{ $voucher->status_badge }}">{{ $voucher->status }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No vouchers yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
