<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WiFi Hotspot') — Get Online</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .portal-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
        .plan-card { border: 2px solid #dee2e6; border-radius: 12px; cursor: pointer; transition: all .2s; }
        .plan-card:hover, .plan-card.selected { border-color: #7c3aed; background: #f5f3ff; }
        .plan-card.selected { box-shadow: 0 0 0 3px rgba(124,58,237,.25); }
        .badge-type { font-size: .65rem; letter-spacing: .08em; text-transform: uppercase; }
        .price-tag { font-size: 1.6rem; font-weight: 700; color: #7c3aed; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
    <div class="container" style="max-width: 540px;">
        <div class="text-center text-white mb-4">
            <i class="bi bi-wifi fs-1"></i>
            <h2 class="fw-bold mt-1">WiFi Hotspot</h2>
            <p class="opacity-75">Buy a voucher to get online instantly</p>
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
