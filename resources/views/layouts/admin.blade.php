<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
    @include('partials.favicon')
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <style>
        .sidebar { width: 240px; min-height: 100vh; background: #1a1a2e; }
        .sidebar .nav-link { color: rgba(255,255,255,.7); padding: .5rem 1.25rem; border-radius: 6px; margin: 2px 8px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.1); }
        .sidebar .nav-link i { width: 20px; }
        .sidebar-brand { color: #fff; font-weight: 700; font-size: 1.1rem; }
        .main-content { flex: 1; background: #f8f9fa; min-height: 100vh; }
        .stat-card { border-left: 4px solid; border-radius: 8px; }
        .stat-card.primary { border-color: #0d6efd; }
        .stat-card.success { border-color: #198754; }
        .stat-card.warning { border-color: #ffc107; }
        .stat-card.danger  { border-color: #dc3545; }
        .table th { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar d-flex flex-column py-3">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand text-decoration-none px-4 pb-3 d-flex align-items-center gap-2">
            <img src="/images/kirwa-net-mark.png" alt="" width="32" height="32" class="flex-shrink-0">
            <span>{{ config('app.name') }}</span>
        </a>
        <hr class="border-secondary my-1">
        <nav class="nav flex-column gap-1 mt-2">
            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="{{ route('admin.plans.index') }}" class="nav-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
                <i class="bi bi-layers"></i> Plans
            </a>
            <a href="{{ route('admin.vouchers.index') }}" class="nav-link {{ request()->routeIs('admin.vouchers.*') ? 'active' : '' }}">
                <i class="bi bi-ticket-perforated"></i> Vouchers
            </a>
            <a href="{{ route('admin.payments.index') }}" class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                <i class="bi bi-credit-card"></i> Payments
            </a>
        </nav>
        <div class="mt-auto px-4 pb-2">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="main-content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-semibold">@yield('page-title', 'Dashboard')</h4>
                @hasSection('breadcrumb')
                <nav aria-label="breadcrumb" class="mt-1">
                    <ol class="breadcrumb mb-0 small">@yield('breadcrumb')</ol>
                </nav>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small"><i class="bi bi-person-circle"></i> {{ auth()->user()->name }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle-fill me-1"></i> {{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>
</div>
@stack('scripts')
</body>
</html>
