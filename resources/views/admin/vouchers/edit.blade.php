@extends('layouts.admin')
@section('title', 'Edit Voucher')
@section('page-title', 'Edit Voucher '.$voucher->code)

@section('content')
<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.vouchers.update', $voucher) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Customer Name</label>
                <input type="text" name="customer_name" class="form-control"
                       value="{{ old('customer_name', $voucher->customer_name) }}">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Customer Phone</label>
                <input type="text" name="customer_phone" class="form-control"
                       value="{{ old('customer_phone', $voucher->customer_phone) }}">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.vouchers.show', $voucher) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
