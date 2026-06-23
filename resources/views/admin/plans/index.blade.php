@extends('layouts.admin')
@section('title', 'Plans')
@section('page-title', 'Plans')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Plan
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Duration</th>
                    <th>Price (KES)</th>
                    <th>Bandwidth</th>
                    <th>Vouchers</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                <tr>
                    <td class="fw-semibold">{{ $plan->name }}</td>
                    <td><span class="badge bg-primary-subtle text-primary text-capitalize">{{ $plan->type }}</span></td>
                    <td>{{ $plan->duration_label }}</td>
                    <td>{{ number_format($plan->price, 2) }}</td>
                    <td class="font-monospace small">↑{{ $plan->bandwidth_up }} / ↓{{ $plan->bandwidth_down }}</td>
                    <td>{{ $plan->vouchers_count }}</td>
                    <td>
                        @if($plan->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.plans.show', $plan) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="d-inline"
                              onsubmit="return confirm('Delete this plan?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-5">No plans yet. <a href="{{ route('admin.plans.create') }}">Create one.</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($plans->hasPages())
    <div class="card-footer bg-white">{{ $plans->links() }}</div>
    @endif
</div>
@endsection
