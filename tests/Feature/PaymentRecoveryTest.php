<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mpesa.env' => 'sandbox',
            'mpesa.consumer_key' => 'test-key',
            'mpesa.consumer_secret' => 'test-secret-key',
            'mpesa.shortcode' => '174379',
            'mpesa.passkey' => 'test-passkey',
            'mpesa.callback_secret' => 'test-secret',
            'mpesa.callback_base_url' => 'https://example.test',
        ]);
    }

    public function test_a_stale_payment_is_expired_when_the_daraja_query_fails(): void
    {
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'token'], 200),
            'sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query' => Http::response(['errorMessage' => 'Rate limit'], 429),
        ]);

        $payment = $this->makePayment();
        $payment->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $this->artisan('payments:recover')->assertExitCode(0);

        $payment->refresh();

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->status);
        $this->assertStringContainsString('could not be confirmed', $payment->result_desc);
        $this->assertSame(0, Voucher::count());
    }

    public function test_a_recent_payment_stays_pending_when_the_daraja_query_fails(): void
    {
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'token'], 200),
            'sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query' => Http::response([], 500),
        ]);

        $payment = $this->makePayment();
        $payment->forceFill(['created_at' => now()->subMinutes(2)])->save();

        $this->artisan('payments:recover')->assertExitCode(0);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(0, Voucher::count());
    }

    public function test_a_very_old_pending_payment_expires_without_querying_daraja(): void
    {
        Http::fake();

        $payment = $this->makePayment();
        $payment->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->artisan('payments:recover')->assertExitCode(0);

        Http::assertNothingSent();

        $payment->refresh();

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->status);
        $this->assertSame('Payment expired before M-Pesa confirmation could be checked', $payment->result_desc);
        $this->assertSame(0, Voucher::count());
    }

    private function makePayment(): Payment
    {
        $plan = Plan::create([
            'name' => 'Recovery Plan',
            'type' => 'daily',
            'duration_hours' => 1,
            'price' => 20,
            'bandwidth_up' => '2M',
            'bandwidth_down' => '2M',
            'is_active' => true,
        ]);

        return Payment::create([
            'plan_id' => $plan->id,
            'phone' => '254712345678',
            'amount' => $plan->price,
            'status' => Payment::STATUS_PENDING,
            'merchant_request_id' => 'merchant-'.uniqid(),
            'checkout_request_id' => 'checkout-'.uniqid(),
        ]);
    }
}
