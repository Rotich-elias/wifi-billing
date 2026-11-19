<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Buy WiFi') — {{ config('app.name') }}</title>
    @include('partials.favicon')
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <style>
        body { background: linear-gradient(135deg, #042F2E 0%, #0F766E 55%, #115E59 100%); min-height: 100vh; }
        .portal-card { border-radius: 16px; border-top: 4px solid #14B8A6; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        .plan-card { border: 2px solid #dee2e6; border-radius: 12px; cursor: pointer; transition: all .2s; }
        .plan-card:hover, .plan-card.selected { border-color: #0D9488; background: #F0FDFA; }
        .plan-card.selected { box-shadow: 0 0 0 3px rgba(13,148,136,.25); }
        .badge-type { font-size: .65rem; letter-spacing: .08em; text-transform: uppercase; }
        .price-tag { font-size: 1.6rem; font-weight: 700; color: #0F766E; }
        .brand-mark { filter: drop-shadow(0 6px 16px rgba(0,0,0,.28)); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
    <div class="container" style="max-width: 540px;">
        <div class="text-center text-white mb-4">
            <img src="/images/teto-net-mark.png" alt="{{ config('app.name') }}" width="84" height="84" class="brand-mark mb-2">
            <h2 class="fw-bold mt-1 mb-0">{{ config('app.name') }}</h2>
            <p class="opacity-75 mb-0">{{ config('app.tagline') }}</p>
        </div>
        <div class="card portal-card border-0">
            <div class="card-body p-4">
                @if(session('success'))
                    <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
