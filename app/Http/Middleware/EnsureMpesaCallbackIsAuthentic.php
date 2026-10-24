<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnsureMpesaCallbackIsAuthentic
{
    /**
     * M-Pesa does not sign callbacks. The secret is part of the URL path so it
     * does not end up in proxy access logs as a query string.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('mpesa.callback_secret', '');

        if ($secret === '') {
            return $this->deny();
        }

        $provided = (string) $request->route('secret', '');

        if (! hash_equals($secret, $provided)) {
            return $this->deny();
        }

        $allowedIps = (array) config('mpesa.allowed_ips', []);

        if ($allowedIps !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowedIps)) {
            return $this->deny();
        }

        return $next($request);
    }

    private function deny(): Response
    {
        // Do not reveal that the callback route exists to unauthenticated callers.
        return response()->json([
            'ResultCode' => 1,
            'ResultDesc' => 'Not found',
        ], 404);
    }
}
