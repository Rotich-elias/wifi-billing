<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login — WiFi Billing</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="card border-0 shadow-lg" style="width: 380px; border-radius: 16px;">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="bi bi-wifi fs-1 text-primary"></i>
                <h4 class="fw-bold mt-2">WiFi Billing</h4>
                <p class="text-muted small">Admin Login</p>
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
