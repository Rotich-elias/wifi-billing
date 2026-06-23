@extends('layouts.admin')
@section('title', 'Voucher '.$voucher->code)
@section('page-title', 'Voucher '.$voucher->code)

@section('content')
<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body text-center p-4">
                <div class="font-monospace fs-3 fw-bold text-primary mb-1">{{ $voucher->code }}</div>
                <span class="badge bg-{{ $voucher->status_badge }} fs-6 mb-3">{{ $voucher->status }}</span>
                <div class="small text-muted">
                    <div>Plan: <strong>{{ $voucher->plan->name }}</strong></div>
                    <div>Price: <strong>KES {{ number_format($voucher->plan->price, 2) }}</strong></div>
                    <div>Duration: <strong>{{ $voucher->plan->duration_label }}</strong></div>
                </div>
                <hr>
                @if($voucher->status === 'unused')
                <div class="d-grid gap-2">
                    <form method="POST" action="{{ route('admin.vouchers.sync', $voucher) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-arrow-repeat"></i> Push to MikroTik
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.vouchers.destroy', $voucher) }}"
                          onsubmit="return confirm('Revoke this voucher?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-x-circle"></i> Revoke
                        </button>
                    </form>
                </div>
                @elseif($voucher->status === 'active')
                <form method="POST" action="{{ route('admin.vouchers.sync', $voucher) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning w-100">
                        <i class="bi bi-wifi-off"></i> Remove from MikroTik
                    </button>
                </form>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted mb-3">Details</h6>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted">Customer</dt>
                    <dd class="col-7">{{ $voucher->customer_name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Phone</dt>
                    <dd class="col-7">{{ $voucher->customer_phone ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Activated</dt>
                    <dd class="col-7">{{ $voucher->activated_at?->format('d M Y H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Expires</dt>
                    <dd class="col-7">{{ $voucher->expires_at?->format('d M Y H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">MikroTik</dt>
                    <dd class="col-7">
                        @if($voucher->mikrotik_synced)
                            <span class="text-success"><i class="bi bi-check-circle-fill"></i> Synced</span>
                        @else
                            <span class="text-secondary">Not synced</span>
                        @endif
                    </dd>
                    <dt class="col-5 text-muted">Batch</dt>
                    <dd class="col-7 font-monospace" style="font-size:.7rem">{{ substr($voucher->batch_id ?? '—', 0, 8) }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Payment History</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Phone</th><th>Amount</th><th>Status</th><th>Receipt</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @forelse($voucher->payments as $payment)
                        <tr>
                            <td>{{ $payment->phone }}</td>
                            <td>KES {{ number_format($payment->amount, 2) }}</td>
                            <td><span class="badge bg-{{ $payment->status_badge }}">{{ $payment->status }}</span></td>
                            <td class="font-monospace small">{{ $payment->mpesa_receipt ?? '—' }}</td>
                            <td class="text-muted small">{{ $payment->created_at->format('d M Y H:i') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No payments.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0">MikroTik Sync Log</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Action</th><th>Status</th><th>Message</th><th>When</th></tr>
                    </thead>
                    <tbody>
                        @forelse($voucher->syncLogs as $log)
                        <tr>
                            <td class="text-capitalize">{{ $log->action }}</td>
                            <td>
                                <span class="badge bg-{{ $log->status === 'success' ? 'success' : 'danger' }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $log->message }}</td>
                            <td class="small text-muted">{{ $log->created_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No sync logs.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
