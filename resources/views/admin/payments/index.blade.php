@extends('layouts.admin')
@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Total Revenue</div>
                <div class="fs-5 fw-bold text-success">KES {{ number_format($summary['total'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Today</div>
                <div class="fs-5 fw-bold">KES {{ number_format($summary['today'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Pending</div>
                <div class="fs-5 fw-bold text-warning">{{ $summary['pending'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Failed</div>
                <div class="fs-5 fw-bold text-danger">{{ $summary['failed'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Phone, receipt…" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach(['pending','paid','failed','expired'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('admin.payments.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Phone</th>
                    <th>Voucher</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Receipt</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->phone }}</td>
                    <td class="font-monospace small">
                        @if($payment->voucher)
                        <a href="{{ route('admin.vouchers.show', $payment->voucher) }}">
                            {{ $payment->voucher->code }}
                        </a>
                        @else —
                        @endif
                    </td>
                    <td class="small">{{ $payment->plan?->name ?? $payment->voucher?->plan?->name ?? '—' }}</td>
                    <td class="fw-semibold">KES {{ number_format($payment->amount, 2) }}</td>
                    <td><span class="badge bg-{{ $payment->status_badge }}">{{ $payment->status }}</span></td>
                    <td class="font-monospace small text-muted">{{ $payment->mpesa_receipt ?? '—' }}</td>
                    <td class="small text-muted">{{ $payment->created_at->format('d M Y H:i') }}</td>
                    <td>
                        <a href="{{ route('admin.payments.show', $payment) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if($payment->status === 'pending')
                        <form method="POST" action="{{ route('admin.payments.query', $payment) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning" title="Query STK status">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-5">No payments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payments->hasPages())
    <div class="card-footer bg-white">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
