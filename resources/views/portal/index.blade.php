@extends('layouts.portal')
@section('title', 'Buy WiFi')

@section('content')
<h5 class="fw-semibold mb-1">Buy WiFi Access</h5>
<p class="text-muted small mb-3">Select a plan, enter your Safaricom number, and pay via M-Pesa.</p>

@if($plans->isEmpty())
    <div class="alert alert-warning small mb-0">
        No plans are available right now. Please contact the hotspot operator.
    </div>
@else
<form method="POST" action="{{ route('portal.purchase') }}" id="purchaseForm">
    @csrf

    @foreach(['mac' => 'mac', 'ip' => 'ip', 'login_url' => 'login_url', 'orig_url' => 'orig_url'] as $field => $key)
        @if(old($field) || !empty($hotspot[$key]))
        <input type="hidden" name="{{ $field }}" value="{{ old($field, $hotspot[$key]) }}">
        @endif
    @endforeach

    <div class="mb-3">
        <label class="form-label fw-semibold small">Choose a Plan</label>
        <div class="row g-2">
            @foreach($plans as $plan)
            <div class="col-6">
                <label class="plan-card p-3 d-block {{ old('plan_id') == $plan->id ? 'selected' : '' }}"
                       for="plan_{{ $plan->id }}">
                    <input type="radio" name="plan_id" id="plan_{{ $plan->id }}"
                           value="{{ $plan->id }}" class="d-none plan-radio"
                           {{ old('plan_id') == $plan->id ? 'checked' : '' }} required>
                    <span class="badge badge-type bg-primary-subtle text-primary mb-1">{{ $plan->type }}</span>
                    <div class="fw-semibold">{{ $plan->name }}</div>
                    <div class="price-tag">KES {{ number_format($plan->price, 0) }}</div>
                    <div class="text-muted small">
                        {{ $plan->duration_label }} &bull; ↓{{ $plan->bandwidth_down }}
                        @if($plan->data_limit)
                            &bull; {{ $plan->data_limit }}
                        @endif
                    </div>
                    @if($plan->description)
                    <div class="text-muted" style="font-size:.75rem">{{ $plan->description }}</div>
                    @endif
                </label>
            </div>
            @endforeach
        </div>
        @error('plan_id')
        <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="phone" class="form-label fw-semibold small">M-Pesa Phone Number</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input type="tel" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror"
                   placeholder="07XX XXX XXX" value="{{ old('phone') }}" required>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">You'll receive an STK Push prompt on this number.</div>
    </div>

    <div class="mb-4">
        <label for="name" class="form-label fw-semibold small">Your Name (optional)</label>
        <input type="text" name="name" id="name" class="form-control"
               placeholder="e.g. John Doe" value="{{ old('name') }}">
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2" id="buyBtn">
        <i class="bi bi-phone-vibrate me-1"></i> Pay with M-Pesa
    </button>
</form>
@endif

<hr class="my-4">

<div class="text-center">
    <p class="text-muted small mb-2">Already have a code? Connect now:</p>
    <form method="POST" action="{{ route('portal.check') }}" class="d-flex gap-2">
        @csrf
        @if(old('login_url') || !empty($hotspot['login_url']))
        <input type="hidden" name="login_url" value="{{ old('login_url', $hotspot['login_url']) }}">
        @endif
        @if(old('orig_url') || !empty($hotspot['orig_url']))
        <input type="hidden" name="orig_url" value="{{ old('orig_url', $hotspot['orig_url']) }}">
        @endif
        <input type="text" name="code" class="form-control font-monospace text-uppercase @error('code') is-invalid @enderror"
               placeholder="8-character code" maxlength="12" autocomplete="off" required>
        <button class="btn btn-outline-secondary">Connect</button>
    </form>
    @error('code')
    <div class="text-danger small mt-1 text-start">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
document.querySelectorAll('.plan-radio').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
        r.closest('.plan-card').classList.add('selected');
    });
});
const purchaseForm = document.getElementById('purchaseForm');
if (purchaseForm) {
    purchaseForm.addEventListener('submit', function() {
        const btn = document.getElementById('buyBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending M-Pesa request…';
    });
}
</script>
@endpush
@endsection
