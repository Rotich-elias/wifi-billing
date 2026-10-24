<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login — {{ config('app.name') }}</title>
    @include('partials.favicon')
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <style>
        body { background: linear-gradient(135deg, #071f3f 0%, #0b2e59 60%, #1173b8 100%); min-height: 100vh; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="card border-0 shadow-lg bg-white" style="width: 380px; border-radius: 16px;">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h1 class="visually-hidden">{{ config('app.name') }} Admin Login</h1>
                <img src="/images/kirwa-net-logo.png" alt="{{ config('app.name') }} — Fast. Reliable. Wi-Fi."
                     class="img-fluid mb-3" style="max-width: 175px;">
                <p class="text-muted small mb-0">Admin Login</p>
            </div>

            @if ($errors->any())
            <div class="alert alert-danger py-2 small">
                @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
                @endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="{{ old('email') }}" placeholder="admin@wifi.local" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Log In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
