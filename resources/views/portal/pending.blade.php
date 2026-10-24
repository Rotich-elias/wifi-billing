@extends('layouts.portal')
@section('title', 'Payment Pending')

@section('content')
<div class="text-center py-3">
    <div class="mb-3">
        <span class="display-4">📱</span>
    </div>
    <h5 class="fw-bold">Check Your Phone!</h5>
    <p class="text-muted">An M-Pesa STK Push has been sent to <strong>{{ $payment->phone }}</strong>. Enter your PIN to complete payment.</p>

    <div class="bg-light rounded p-3 mb-3 text-start small">
        <div class="row">
            <div class="col-5 text-muted">Plan</div>
            <div class="col-7 fw-semibold">{{ $voucher->plan->name }}</div>
            <div class="col-5 text-muted">Amount</div>
            <div class="col-7 fw-semibold">KES {{ number_format($payment->amount, 2) }}</div>
        </div>
    </div>

    <p class="text-muted small">After payment is confirmed, your voucher code will be sent to your phone by SMS. You can also refresh the status page below.</p>

    <a href="{{ route('portal.status', ['voucher' => $voucher->access_token]) }}" class="btn btn-primary w-100 mb-2">
        <i class="bi bi-arrow-clockwise me-1"></i> Check Payment Status
    </a>
    <a href="{{ route('portal.index') }}" class="btn btn-outline-secondary w-100">
        &larr; Back
    </a>
</div>
@endsection
