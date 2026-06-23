@extends('layouts.admin')
@section('title', 'Vouchers')
@section('page-title', 'Vouchers')

@section('content')
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search code, phone, name..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach(['unused','active','expired','revoked'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="plan_id" class="form-select form-select-sm">
                    <option value="">All plans</option>
                    @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" {{ request('plan_id') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('admin.vouchers.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('admin.vouchers.create') }}" class="btn btn-sm btn-success">
                    <i class="bi bi-plus-lg"></i> Generate Vouchers
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <form method="POST" action="{{ route('admin.vouchers.bulk') }}" id="bulkForm">
            @csrf
            <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2" id="bulkBar" style="display:none !important">
                <span class="text-muted small" id="bulkCount">0 selected</span>
                <select name="action" class="form-select form-select-sm w-auto">
                    <option value="revoke">Revoke</option>
                    <option value="sync">Sync to MikroTik</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Apply bulk action?')">Apply</button>
            </div>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                        <th>Code</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Customer</th>
                        <th>MikroTik</th>
                        <th>Expires</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vouchers as $voucher)
                    <tr>
                        <td><input type="checkbox" name="voucher_ids[]" value="{{ $voucher->id }}" class="form-check-input row-check"></td>
                        <td class="font-monospace fw-semibold">
                            <a href="{{ route('admin.vouchers.show', $voucher) }}" class="text-decoration-none">{{ $voucher->code }}</a>
                        </td>
                        <td class="small">{{ $voucher->plan->name }}</td>
                        <td><span class="badge bg-{{ $voucher->status_badge }}">{{ $voucher->status }}</span></td>
                        <td class="small text-muted">{{ $voucher->customer_name ?? $voucher->customer_phone ?? '—' }}</td>
                        <td>
                            @if($voucher->mikrotik_synced)
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check2"></i> Synced</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Not synced</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $voucher->expires_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="small text-muted">{{ $voucher->created_at->format('d M Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.vouchers.show', $voucher) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-5">No vouchers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </form>
    </div>
    @if($vouchers->hasPages())
    <div class="card-footer bg-white">{{ $vouchers->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
    updateBulkBar();
});
document.querySelectorAll('.row-check').forEach(c => c.addEventListener('change', updateBulkBar));
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    const bar = document.getElementById('bulkBar');
    bar.style.display = checked > 0 ? 'flex' : 'none';
    document.getElementById('bulkCount').textContent = checked + ' selected';
}
</script>
@endpush
@endsection
