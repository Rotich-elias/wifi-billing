@extends('layouts.portal')
@section('title', 'Buy WiFi')

@section('content')
<h5 class="fw-semibold mb-1">Buy WiFi Access</h5>
<p class="text-muted small mb-3">Select a plan, enter your Safaricom number, and pay via M-Pesa.</p>

<form method="POST" action="{{ route('portal.purchase') }}" id="purchaseForm">
    @csrf

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

<hr class="my-4">

<div class="text-center">
    <p class="text-muted small mb-2">Already have a voucher? Check its status:</p>
    <form method="POST" action="{{ route('portal.check') }}" class="d-flex gap-2">
        @csrf
        <input type="text" name="code" class="form-control font-monospace text-uppercase"
               placeholder="XXXX-XXXX" maxlength="9">
        <button class="btn btn-outline-secondary">Check</button>
    </form>
</div>

@push('scripts')
<script>
document.querySelectorAll('.plan-radio').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
        r.closest('.plan-card').classList.add('selected');
    });
});
document.getElementById('purchaseForm').addEventListener('submit', function() {
    document.getElementById('buyBtn').disabled = true;
    document.getElementById('buyBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing…';
});
</script>
@endpush
@endsection
