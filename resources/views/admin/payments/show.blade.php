@extends('layouts.admin')
@section('title', 'Payment Details')
@section('page-title', 'Payment Details')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <span class="badge bg-{{ $payment->status_badge }} fs-6">{{ strtoupper($payment->status) }}</span>
                <div class="fs-4 fw-bold mt-1">KES {{ number_format($payment->amount, 2) }}</div>
            </div>
            @if($payment->status === 'pending')
            <form method="POST" action="{{ route('admin.payments.query', $payment) }}">
                @csrf
                <button class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-arrow-repeat"></i> Re-check Status
                </button>
            </form>
            @endif
        </div>

        <dl class="row">
            <dt class="col-sm-4 text-muted">Phone</dt>
            <dd class="col-sm-8">{{ $payment->phone }}</dd>

            <dt class="col-sm-4 text-muted">Voucher</dt>
            <dd class="col-sm-8">
                @if($payment->voucher)
                <a href="{{ route('admin.vouchers.show', $payment->voucher) }}" class="font-monospace">
                    {{ $payment->voucher->code }}
                </a>
                — {{ $payment->voucher->plan->name }}
                @else —
                @endif
            </dd>

            <dt class="col-sm-4 text-muted">M-Pesa Receipt</dt>
            <dd class="col-sm-8 font-monospace">{{ $payment->mpesa_receipt ?? '—' }}</dd>

            <dt class="col-sm-4 text-muted">Merchant Req ID</dt>
            <dd class="col-sm-8 font-monospace small">{{ $payment->merchant_request_id ?? '—' }}</dd>

            <dt class="col-sm-4 text-muted">Checkout Req ID</dt>
            <dd class="col-sm-8 font-monospace small">{{ $payment->checkout_request_id ?? '—' }}</dd>

            <dt class="col-sm-4 text-muted">Result Code</dt>
            <dd class="col-sm-8">{{ $payment->result_code ?? '—' }}</dd>

            <dt class="col-sm-4 text-muted">Result Description</dt>
            <dd class="col-sm-8">{{ $payment->result_desc ?? '—' }}</dd>

            <dt class="col-sm-4 text-muted">Paid At</dt>
            <dd class="col-sm-8">{{ $payment->paid_at?->format('d M Y H:i:s') ?? '—' }}</dd>

            <dt class="col-sm-4 text-muted">Created At</dt>
            <dd class="col-sm-8">{{ $payment->created_at->format('d M Y H:i:s') }}</dd>
        </dl>

        @if($payment->raw_callback)
        <details class="mt-3">
            <summary class="text-muted small cursor-pointer">Raw Callback Payload</summary>
            <pre class="bg-light p-3 rounded small mt-2" style="max-height:300px;overflow:auto">{{ json_encode($payment->raw_callback, JSON_PRETTY_PRINT) }}</pre>
        </details>
        @endif

        <div class="mt-3">
            <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary btn-sm">
                &larr; Back to Payments
            </a>
        </div>
    </div>
</div>
</div>
</div>
@endsection
