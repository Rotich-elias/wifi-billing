<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnsureMpesaCallbackIsAuthentic
{
    /**
     * M-Pesa does not sign callbacks, so the callback URL carries a shared
     * secret and, optionally, requests are restricted to Safaricom IP ranges.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('mpesa.callback_secret', '');

        if ($secret !== '') {
            $provided = (string) $request->query('secret', '');

            if (! hash_equals($secret, $provided)) {
                return $this->deny();
            }
        }

        $allowedIps = (array) config('mpesa.allowed_ips', []);

        if ($allowedIps !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowedIps)) {
            return $this->deny();
        }

        return $next($request);
    }

    private function deny(): Response
    {
        return response()->json([
            'ResultCode' => 1,
            'ResultDesc' => 'Forbidden',
        ], 403);
    }
}
