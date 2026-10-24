<?php

namespace Tests\Feature;

use App\Jobs\ExpireVoucherJob;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mpesa.callback_secret' => 'test-secret',
            'mpesa.allowed_ips' => [],
        ]);
    }

    public function test_voucher_status_is_only_reachable_with_the_access_token(): void
    {
        $voucher = $this->makeVoucher();

        $this->get("/voucher/{$voucher->id}/status")->assertNotFound();
        $this->get("/voucher/{$voucher->access_token}/status")->assertOk();
    }

    public function test_admin_area_requires_an_administrator(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->save();

        $this->actingAs($user)->get('/admin')->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_mpesa_callback_requires_the_shared_secret(): void
    {
        $payment = $this->makePayment();

        $this->postJson('/api/mpesa/callback', $this->callbackPayload($payment, 20))
            ->assertForbidden();

        $this->postJson('/api/mpesa/callback?secret=wrong', $this->callbackPayload($payment, 20))
            ->assertForbidden();
    }

    public function test_malformed_mpesa_callback_is_rejected(): void
    {
        $this->postJson('/api/mpesa/callback?secret=test-secret', [])
            ->assertStatus(422);
    }

    public function test_mpesa_callback_amount_must_match_the_payment(): void
    {
        Queue::fake();

        $payment = $this->makePayment();

        $this->postJson('/api/mpesa/callback?secret=test-secret', $this->callbackPayload($payment, 1))
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('vouchers', [
            'id' => $payment->voucher_id,
            'status' => 'unused',
        ]);
    }

    public function test_successful_mpesa_callback_activates_the_voucher_once(): void
    {
        Queue::fake();

        $payment = $this->makePayment();
        $payload = $this->callbackPayload($payment, 20);

        $this->postJson('/api/mpesa/callback?secret=test-secret', $payload)->assertOk();

        $voucher = $payment->voucher->fresh();

        $this->assertSame('active', $voucher->status);
        $this->assertNotNull($voucher->expires_at);

        $expiresAt = $voucher->expires_at->toDateTimeString();

        // Replaying the same callback must not extend the voucher.
        $this->postJson('/api/mpesa/callback?secret=test-secret', $payload)->assertOk();

        $this->assertSame($expiresAt, $voucher->fresh()->expires_at->toDateTimeString());
        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_expiry_job_marks_expired_and_removes_the_router_user(): void
    {
        $voucher = $this->makeVoucher();
        $voucher->update([
            'status' => 'active',
            'expires_at' => now()->subMinute(),
            'mikrotik_synced' => true,
            'mikrotik_user' => $voucher->code,
        ]);

        $mikrotik = Mockery::mock(MikrotikService::class);
        $mikrotik->shouldReceive('syncVoucher')
            ->once()
            ->with(Mockery::on(fn (Voucher $v) => $v->id === $voucher->id), 'remove')
            ->andReturnTrue();

        (new ExpireVoucherJob($voucher))->handle($mikrotik);

        $this->assertSame('expired', $voucher->fresh()->status);
    }

    public function test_status_page_queues_expiry_without_mutating_status_directly(): void
    {
        Queue::fake();

        $voucher = $this->makeVoucher();
        $voucher->update([
            'status' => 'active',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($voucher->checkAndExpire());
        $this->assertSame('active', $voucher->fresh()->status);

        Queue::assertPushed(ExpireVoucherJob::class);
    }

    public function test_inactive_plans_cannot_be_purchased(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $plan = $this->makePlan(['is_active' => false]);

        $this->post('/purchase', [
            'plan_id' => $plan->id,
            'phone' => '0712345678',
        ])->assertNotFound();
    }

    public function test_voucher_lookup_is_rate_limited(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        for ($i = 0; $i < 10; $i++) {
            $this->post('/check', ['code' => 'NOPE'])->assertRedirect();
        }

        $this->post('/check', ['code' => 'NOPE'])->assertStatus(429);
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Test Plan',
            'type' => 'daily',
            'duration_hours' => 1,
            'price' => 20,
            'bandwidth_up' => '2M',
            'bandwidth_down' => '2M',
            'is_active' => true,
        ], $overrides));
    }

    private function makeVoucher(array $overrides = []): Voucher
    {
        return Voucher::create(array_merge([
            'plan_id' => $this->makePlan()->id,
            'status' => 'unused',
        ], $overrides));
    }

    private function makePayment(): Payment
    {
        $voucher = $this->makeVoucher();

        return Payment::create([
            'voucher_id' => $voucher->id,
            'phone' => '254712345678',
            'amount' => 20,
            'status' => 'pending',
            'merchant_request_id' => 'merchant-'.uniqid(),
            'checkout_request_id' => 'checkout-'.uniqid(),
        ]);
    }

    private function callbackPayload(Payment $payment, int $amount): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => $payment->merchant_request_id,
                    'CheckoutRequestID' => $payment->checkout_request_id,
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => $amount],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST-'.uniqid()],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];
    }
}
