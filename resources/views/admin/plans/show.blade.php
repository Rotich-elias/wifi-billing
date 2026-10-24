@extends('layouts.admin')
@section('title', $plan->name)
@section('page-title', $plan->name)

@section('content')
<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted mb-3">Plan Details</h6>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Type</dt>
                    <dd class="col-7 text-capitalize">{{ $plan->type }}</dd>
                    <dt class="col-5 text-muted">Duration</dt>
                    <dd class="col-7">{{ $plan->duration_label }}</dd>
                    <dt class="col-5 text-muted">Price</dt>
                    <dd class="col-7 fw-semibold">KES {{ number_format($plan->price, 2) }}</dd>
                    <dt class="col-5 text-muted">Bandwidth</dt>
                    <dd class="col-7 font-monospace small">↑{{ $plan->bandwidth_up }} / ↓{{ $plan->bandwidth_down }}</dd>
                    <dt class="col-5 text-muted">Data limit</dt>
                    <dd class="col-7">{{ $plan->data_limit ?? 'Unlimited' }}</dd>
                    <dt class="col-5 text-muted">Vouchers</dt>
                    <dd class="col-7">{{ $plan->vouchers_count }}</dd>
                    <dt class="col-5 text-muted">Status</dt>
                    <dd class="col-7">
                        <span class="badge bg-{{ $plan->is_active ? 'success' : 'secondary' }}">
                            {{ $plan->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </dd>
                </dl>
                <div class="mt-3 d-flex gap-2">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Vouchers for this Plan</h6>
                <a href="{{ route('admin.vouchers.create') }}" class="btn btn-sm btn-primary">Generate Vouchers</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Code</th><th>Status</th><th>Customer</th><th>Expires</th></tr>
                    </thead>
                    <tbody>
                        @forelse($vouchers as $voucher)
                        <tr>
                            <td class="font-monospace">
                                <a href="{{ route('admin.vouchers.show', $voucher) }}">{{ $voucher->code }}</a>
                            </td>
                            <td><span class="badge bg-{{ $voucher->status_badge }}">{{ $voucher->status }}</span></td>
                            <td>{{ $voucher->customer_name ?? $voucher->customer_phone ?? '—' }}</td>
                            <td class="text-muted small">{{ $voucher->expires_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No vouchers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($vouchers->hasPages())
            <div class="card-footer bg-white">{{ $vouchers->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
