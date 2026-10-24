<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Voucher;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class VoucherPortalController extends Controller
{
    public function __construct(private MpesaService $mpesa) {}

    public function index(Request $request): View
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();

        $hotspot = [
            'mac' => $request->query('mac'),
            'ip' => $request->query('ip'),
            'login_url' => $request->query('link-login-only'),
            'orig_url' => $request->query('link-orig'),
        ];

        $this->recordHotspotHost($hotspot['login_url']);

        return view('portal.index', compact('plans', 'hotspot'));
    }

    /**
     * Remember the link-login-only host so `php artisan hotspot:hosts` can
     * report it even before the customer completes a payment.
     */
    private function recordHotspotHost(?string $url): void
    {
        if (! $url) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return;
        }

        $host = rtrim(strtolower(trim($host, '[]')), '.');

        if ($host === '') {
            return;
        }

        try {
            $observed = Cache::get('hotspot:observed-hosts', []);
            $observed = is_array($observed) ? $observed : [];

            $entry = $observed[$host] ?? [
                'url' => $url,
                'count' => 0,
                'first_seen' => now()->toIso8601String(),
            ];

            $entry['url'] = $url;
            $entry['count'] = ((int) ($entry['count'] ?? 0)) + 1;
            $entry['last_seen'] = now()->toIso8601String();
            $observed[$host] = $entry;

            Cache::put('hotspot:observed-hosts', $observed, now()->addDays(30));

            if (! Voucher::isSafeLoginUrl($url) && Cache::add('hotspot:warned:'.$host, true, now()->addHours(6))) {
                Log::warning('Hotspot login host is not allowlisted', ['host' => $host]);
            }
        } catch (\Throwable $e) {
            // Diagnostics must never break the customer portal.
            Log::debug('Could not record hotspot host', ['error' => $e->getMessage()]);
        }
    }

    public function purchase(Request $request): RedirectResponse
    {
        // Customers often type spaces or dashes; validate the digits only.
        $request->merge([
            'phone' => preg_replace('/[^\d+]/', '', (string) $request->input('phone')),
        ]);

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'phone' => ['required', 'regex:/^(?:\+?254|0)(?:7|1)\d{8}$/'],
            'name' => 'nullable|string|max:100',
            'mac' => 'nullable|string|max:17',
            'ip' => 'nullable|ip',
            'login_url' => 'nullable|string|max:2048',
            'orig_url' => 'nullable|string|max:2048',
        ]);

        // Only sell plans that are currently offered.
        $plan = Plan::where('is_active', true)->findOrFail($validated['plan_id']);

        try {
            $payment = $this->mpesa->initiatePayment($plan, $validated['phone'], [
                'name' => $validated['name'] ?? null,
                'mac' => $validated['mac'] ?? null,
                'ip' => $validated['ip'] ?? null,
                'login_url' => $validated['login_url'] ?? null,
                'orig_url' => $validated['orig_url'] ?? null,
            ]);

            return redirect()->to($payment->signedShowUrl());
        } catch (\Throwable $e) {
            Log::error('M-Pesa initiation failed', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'phone' => 'Payment could not be started. Please try again.',
                ]);
        }
    }

    public function showPayment(Payment $payment): View
    {
        $payment->load('plan', 'voucher.plan');

        $redirectUrl = null;

        if ($payment->isPaid() && $payment->voucher?->isProvisioned()) {
            $redirectUrl = $payment->hotspotLoginUrl();
        }

        return view('portal.payment', [
            'payment' => $payment,
            'pollUrl' => $payment->signedStatusUrl(),
            'redirectUrl' => $redirectUrl,
        ]);
    }

    public function paymentStatus(Payment $payment): JsonResponse
    {
        $payment->load('voucher.plan');
        $voucher = $payment->voucher;

        $status = $payment->status;
        $message = match ($status) {
            Payment::STATUS_PENDING => 'Check your phone and enter your M-Pesa PIN.',
            Payment::STATUS_PAID => 'Payment received. Activating your voucher...',
            Payment::STATUS_FAILED => 'Payment was not completed. Please try again.',
            Payment::STATUS_EXPIRED => 'Payment expired. Please try again.',
            default => 'Unknown payment status.',
        };

        if ($voucher && in_array($voucher->status, ['expired', 'revoked'], true)) {
            $status = Payment::STATUS_EXPIRED;
            $message = 'This voucher is no longer active.';
        } elseif ($voucher && $voucher->status === 'active' && $voucher->isExpired()) {
            $voucher->checkAndExpire();
            $status = Payment::STATUS_EXPIRED;
            $message = 'This voucher has expired.';
        }

        $provisioned = $payment->isPaid() && ($voucher?->isProvisioned() ?? false);
        $code = $provisioned ? $voucher->code : null;
        $redirectUrl = null;

        if ($provisioned) {
            $message = 'Payment confirmed. Your voucher is ready.';
            $redirectUrl = $payment->hotspotLoginUrl();
        }

        return response()->json([
            'status' => $status,
            'voucher_status' => $voucher?->status,
            'provisioned' => $provisioned,
            'code' => $code,
            'redirect_url' => $redirectUrl,
            'message' => $message,
        ]);
    }

    /**
     * "Have a code?" flow for returning customers.
     */
    public function check(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'login_url' => 'nullable|string|max:2048',
            'orig_url' => 'nullable|string|max:2048',
        ]);

        $raw = strtoupper(trim($validated['code']));
        $normalised = Voucher::normaliseCode($raw);

        $voucher = Voucher::with('plan')
            ->where('code', $raw)
            ->orWhere('code', $normalised)
            ->first();

        if (! $voucher) {
            // Same response for unknown codes to avoid an existence oracle.
            return back()->withErrors(['code' => 'Voucher code not found.']);
        }

        if ($voucher->status === 'unused') {
            return back()->withErrors(['code' => 'This code has not been activated yet.']);
        }

        if (in_array($voucher->status, ['expired', 'revoked'], true)) {
            return back()->withErrors(['code' => 'This code has expired or is no longer valid.']);
        }

        if ($voucher->isExpired()) {
            $voucher->checkAndExpire();

            return back()->withErrors(['code' => 'This code has expired.']);
        }

        if (! $voucher->isProvisioned()) {
            return back()->withErrors(['code' => 'This code is still being activated. Please try again in a moment.']);
        }

        $loginUrl = $voucher->hotspotLoginUrl(
            $validated['login_url'] ?? null,
            $validated['orig_url'] ?? null,
        );

        if ($loginUrl) {
            return redirect()->away($loginUrl);
        }

        // No hotspot parameters (for example, the customer opened the portal
        // directly). Show the code and login instructions instead.
        return redirect()->route('portal.status', ['voucher' => $voucher->access_token]);
    }

    public function status(Voucher $voucher): View
    {
        $voucher->checkAndExpire();
        $voucher->load('plan', 'payment');

        return view('portal.status', compact('voucher'));
    }
}
