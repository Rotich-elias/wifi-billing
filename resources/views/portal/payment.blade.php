@extends('layouts.portal')
@section('title', 'Payment Status')

@section('content')
@php
    $plan = $payment->plan ?? $payment->voucher?->plan;
    $initialProvisioned = $payment->isPaid() && $payment->voucher?->isProvisioned();
    $voucherExpired = $payment->voucher
        && (in_array($payment->voucher->status, ['expired', 'revoked'], true)
            || $payment->voucher->isExpired());
    $terminalFailure = in_array($payment->status, ['failed', 'expired'], true) || $voucherExpired;
    $retryUrl = route('portal.index', array_filter([
        'mac' => $payment->mac,
        'ip' => $payment->ip,
        'link-login-only' => $payment->login_url,
        'link-orig' => $payment->orig_url,
    ], fn ($value) => filled($value)));
@endphp

<div id="statusPanel" class="text-center py-2">
    <div id="statusIcon" class="display-4 mb-2">
        @if($initialProvisioned)
            ✅
        @elseif($terminalFailure)
            ❌
        @elseif($payment->isPaid())
            ⏳
        @else
            📱
        @endif
    </div>
    <h5 id="statusTitle" class="fw-bold">
        @if($initialProvisioned)
            You're ready!
        @elseif($terminalFailure)
            Payment not completed
        @elseif($payment->isPaid())
            Activating your voucher
        @else
            Check your phone
        @endif
    </h5>
    <p id="statusMessage" class="text-muted mb-3">
        @if($initialProvisioned)
            Your voucher is ready. Redirecting you to the WiFi login…
        @elseif($terminalFailure)
            This payment or voucher is no longer active. You can try again with a new payment.
        @elseif($payment->isPaid())
            Payment received. Creating your WiFi user…
        @else
            An M-Pesa STK Push has been sent to <strong>{{ $payment->phone }}</strong>. Enter your PIN to complete payment.
        @endif
    </p>
</div>

<div class="bg-light rounded p-3 mb-3 text-start small">
    <div class="row g-1">
        <div class="col-5 text-muted">Plan</div>
        <div class="col-7 fw-semibold">{{ $plan?->name ?? 'WiFi plan' }}</div>
        <div class="col-5 text-muted">Amount</div>
        <div class="col-7 fw-semibold">KES {{ number_format($payment->amount, 2) }}</div>
        <div class="col-5 text-muted">Phone</div>
        <div class="col-7">{{ $payment->phone }}</div>
        <div class="col-5 text-muted">Status</div>
        <div class="col-7"><span id="statusBadge" class="badge bg-secondary">{{ $payment->status }}</span></div>
    </div>
</div>

<div id="codeBox" class="alert alert-success text-center {{ $initialProvisioned ? '' : 'd-none' }}">
    <div class="small text-muted mb-1">Your WiFi code</div>
    <div id="voucherCode" class="font-monospace fs-3 fw-bold">{{ $initialProvisioned ? $payment->voucher->code : '' }}</div>
    <div class="small mt-2">Username and password are both this code.</div>
    <div id="redirectNote" class="small text-muted mt-2 {{ $redirectUrl ? '' : 'd-none' }}">
        Redirecting to the WiFi login page…
    </div>
</div>

<div id="failedBox" class="alert alert-danger small {{ $terminalFailure ? '' : 'd-none' }}">
    <i class="bi bi-exclamation-circle me-1"></i>
    <span id="failedMessage">
        {{ $voucherExpired ? 'This voucher has expired. Please buy a new one.' : 'This payment was not completed. Please try again.' }}
    </span>
</div>

<div class="d-grid gap-2">
    <a href="{{ $retryUrl }}" id="retryLink"
       class="btn btn-primary {{ $terminalFailure ? '' : 'd-none' }}">
        <i class="bi bi-arrow-clockwise me-1"></i> Try again
    </a>
    <a href="{{ $retryUrl }}" class="btn btn-outline-secondary btn-sm">
        &larr; Back to plans
    </a>
</div>

@push('scripts')
<script>
const statusUrl = @json($pollUrl);
const initialRedirect = @json($redirectUrl);
let timer = null;
let finished = false;
let redirecting = false;

const els = {
    icon: document.getElementById('statusIcon'),
    title: document.getElementById('statusTitle'),
    message: document.getElementById('statusMessage'),
    badge: document.getElementById('statusBadge'),
    codeBox: document.getElementById('codeBox'),
    code: document.getElementById('voucherCode'),
    redirectNote: document.getElementById('redirectNote'),
    failedBox: document.getElementById('failedBox'),
    failedMessage: document.getElementById('failedMessage'),
    retryLink: document.getElementById('retryLink'),
};

function showFailed(message) {
    els.icon.textContent = '❌';
    els.title.textContent = 'Payment not completed';
    els.message.textContent = message;
    els.failedMessage.textContent = message;
    els.failedBox.classList.remove('d-none');
    els.retryLink.classList.remove('d-none');
    els.codeBox.classList.add('d-none');
}

function render(data) {
    els.badge.textContent = data.status;
    els.badge.className = 'badge bg-' + ({
        pending: 'warning',
        paid: 'success',
        failed: 'danger',
        expired: 'secondary',
    }[data.status] || 'secondary');

    if (data.status === 'failed' || data.status === 'expired') {
        showFailed(data.message || 'This payment was not completed. Please try again.');
        finished = true;
        clearInterval(timer);
        return;
    }

    if (data.status === 'paid' && data.provisioned && data.code) {
        els.icon.textContent = '✅';
        els.title.textContent = "You're ready!";
        els.message.textContent = 'Payment confirmed. Your voucher is ready.';
        els.code.textContent = data.code;
        els.codeBox.classList.remove('d-none');
        els.failedBox.classList.add('d-none');
        els.retryLink.classList.add('d-none');
        finished = true;
        clearInterval(timer);

        if (data.redirect_url) {
            els.redirectNote.classList.remove('d-none');
            if (!redirecting) {
                redirecting = true;
                setTimeout(() => { window.location.href = data.redirect_url; }, 2500);
            }
        } else {
            els.redirectNote.classList.add('d-none');
        }
        return;
    }

    if (data.status === 'paid') {
        els.icon.textContent = '⏳';
        els.title.textContent = 'Activating your voucher';
        els.message.textContent = data.message || 'Payment received. Creating your WiFi user…';
        els.codeBox.classList.add('d-none');
        return;
    }

    els.icon.textContent = '📱';
    els.title.textContent = 'Check your phone';
    els.message.textContent = data.message || 'Enter your M-Pesa PIN to complete payment.';
}

async function poll() {
    if (finished) return;

    try {
        const response = await fetch(statusUrl, {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok) {
            throw new Error('Status request failed');
        }

        render(await response.json());
    } catch (error) {
        els.message.textContent = 'Waiting for confirmation… this can take a few seconds.';
    }
}

if (initialRedirect && !finished) {
    els.redirectNote.classList.remove('d-none');
    redirecting = true;
    setTimeout(() => { window.location.href = initialRedirect; }, 2500);
}

poll();
timer = setInterval(poll, 2500);
</script>
@endpush
@endsection
