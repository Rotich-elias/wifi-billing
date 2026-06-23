@extends('layouts.admin')
@section('title', 'Edit Plan')
@section('page-title', 'Edit Plan')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.plans.update', $plan) }}">
            @csrf @method('PUT')
            @include('admin.plans._form')
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Update Plan</button>
                <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
