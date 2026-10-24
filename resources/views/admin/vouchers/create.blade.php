@extends('layouts.admin')
@section('title', 'Generate Vouchers')
@section('page-title', 'Generate Vouchers')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.vouchers.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Plan</label>
                <select name="plan_id" class="form-select @error('plan_id') is-invalid @enderror" required>
                    <option value="">Select a plan…</option>
                    @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                        {{ $plan->name }} — KES {{ number_format($plan->price, 2) }} ({{ $plan->duration_label }})
                    </option>
                    @endforeach
                </select>
                @error('plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Quantity</label>
                <input type="number" name="quantity" class="form-control @error('quantity') is-invalid @enderror"
                       value="{{ old('quantity', 1) }}" min="1" max="500" required>
                <div class="form-text">Maximum 500 at once.</div>
                @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="activate" value="1" id="activate" class="form-check-input"
                       {{ old('activate') ? 'checked' : '' }}>
                <label for="activate" class="form-check-label">
                    <i class="bi bi-lightning-charge"></i> Activate immediately (support voucher)
                </label>
                <div class="form-text">The code becomes active now and is pushed to the router.</div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="print_after" value="1" id="print_after" class="form-check-input"
                       {{ old('print_after') ? 'checked' : '' }}>
                <label for="print_after" class="form-check-label">
                    <i class="bi bi-printer"></i> Open PDF immediately after generating
                </label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-ticket-perforated"></i> Generate
                </button>
                <a href="{{ route('admin.vouchers.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
