@extends('layouts.portal')
@section('title', 'Voucher Status')

@section('content')
<div class="text-center mb-3">
    @if($voucher->status === 'active')
        <span class="display-4">✅</span>
        <h5 class="fw-bold mt-2">You're Online!</h5>
    @elseif($voucher->status === 'expired')
        <span class="display-4">⌛</span>
        <h5 class="fw-bold mt-2">Voucher Expired</h5>
    @elseif($voucher->status === 'unused')
        <span class="display-4">⏳</span>
        <h5 class="fw-bold mt-2">Awaiting Payment</h5>
    @else
        <span class="display-4">❌</span>
        <h5 class="fw-bold mt-2">Voucher Revoked</h5>
    @endif
</div>

<div class="bg-light rounded p-3 mb-3">
    <div class="text-center font-monospace fs-4 fw-bold text-primary mb-2">{{ $voucher->code }}</div>
    <div class="row small g-1">
        <div class="col-5 text-muted">Plan</div>
        <div class="col-7">{{ $voucher->plan->name }}</div>

        <div class="col-5 text-muted">Status</div>
        <div class="col-7"><span class="badge bg-{{ $voucher->status_badge }}">{{ $voucher->status }}</span></div>

        @if($voucher->status === 'active')
        <div class="col-5 text-muted">Activated</div>
        <div class="col-7">{{ $voucher->activated_at?->format('d M Y H:i') }}</div>
        <div class="col-5 text-muted">Expires</div>
        <div class="col-7">{{ $voucher->expires_at?->format('d M Y H:i') }}</div>
        <div class="col-5 text-muted">Time left</div>
        <div class="col-7 fw-semibold text-success">{{ $voucher->expires_at?->diffForHumans() }}</div>
        @endif

        @if($voucher->payment)
        <div class="col-5 text-muted">Payment</div>
        <div class="col-7">
            <span class="badge bg-{{ $voucher->payment->status_badge }}">{{ $voucher->payment->status }}</span>
            @if($voucher->payment->mpesa_receipt)
            — <span class="font-monospace">{{ $voucher->payment->mpesa_receipt }}</span>
            @endif
        </div>
        @endif
    </div>
</div>

@if($voucher->status === 'unused')
<div class="alert alert-warning small">
    <i class="bi bi-info-circle"></i> Payment is being processed. This may take up to 1 minute. Refresh this page to check.
</div>
@endif

@if($voucher->status === 'active')
<div class="alert alert-success small">
    <strong>WiFi Login:</strong><br>
    Username: <code>{{ $voucher->code }}</code><br>
    Password: <code>{{ $voucher->code }}</code>
</div>
@endif

<div class="d-grid gap-2">
    <a href="{{ route('portal.status', ['voucher' => $voucher->access_token]) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </a>
    <a href="{{ route('portal.index') }}" class="btn btn-outline-primary btn-sm">
        Buy Another Voucher
    </a>
</div>
@endsection
