<?php

namespace Tests\Feature;

use App\Contracts\RouterDriver;
use App\Jobs\ExpireVoucherJob;
use App\Jobs\SyncVoucherToMikrotik;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mpesa.callback_secret' => 'test-secret',
            'mpesa.callback_base_url' => 'https://example.test',
            'mpesa.allowed_ips' => [],
            'mpesa.env' => 'sandbox',
            'mpesa.consumer_key' => 'test-key',
            'mpesa.consumer_secret' => 'test-secret-key',
            'mpesa.shortcode' => '174379',
            'mpesa.passkey' => 'test-passkey',
            'router.driver' => 'mock',
            'router.host' => '192.168.100.1',
            'router.allowed_login_hosts' => [],
            'sms.enabled' => false,
        ]);
    }

    public function test_successful_payment_creates_one_voucher_and_provisions_it(): void
    {
        $payment = $this->makePayment();

        $this->postJson('/api/mpesa/callback/test-secret', $this->callbackPayload($payment, 20, 'RECEIPT-1'))
            ->assertOk()
            ->assertJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

        $payment->refresh();
        $voucher = $payment->voucher;

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($voucher);
        $this->assertSame('active', $voucher->status);
        $this->assertTrue($voucher->mikrotik_synced);
        $this->assertDatabaseHas('mock_router_users', ['username' => $voucher->code]);

        // The code is only exposed once provisioning has completed.
        $this->get($payment->signedStatusUrl())
            ->assertOk()
            ->assertJson([
                'status' => 'paid',
                'provisioned' => true,
                'code' => $voucher->code,
            ]);

        $this->assertSame(1, Voucher::where('plan_id', $payment->plan_id)->count());
    }

    public function test_cancelled_stk_creates_no_voucher(): void
    {
        $payment = $this->makePayment();

        $payload = $this->callbackPayload($payment, 20, 'RECEIPT-2');
        $payload['Body']['stkCallback']['ResultCode'] = 1032;
        $payload['Body']['stkCallback']['ResultDesc'] = 'Request cancelled by user';

        $this->postJson('/api/mpesa/callback/test-secret', $payload)->assertOk();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->voucher_id);
        $this->assertSame(0, Voucher::count());
    }

    public function test_duplicate_callback_does_not_create_a_second_voucher(): void
    {
        $payment = $this->makePayment();
        $payload = $this->callbackPayload($payment, 20, 'RECEIPT-3');

        $this->postJson('/api/mpesa/callback/test-secret', $payload)->assertOk();
        $firstVoucher = $payment->fresh()->voucher;

        $this->postJson('/api/mpesa/callback/test-secret', $payload)->assertOk();

        $this->assertSame(1, Voucher::count());
        $this->assertSame($firstVoucher->id, $payment->fresh()->voucher_id);
    }

    public function test_unknown_or_malformed_callbacks_still_return_200(): void
    {
        $this->postJson('/api/mpesa/callback/test-secret', [
            'Body' => ['stkCallback' => ['CheckoutRequestID' => 'does-not-exist', 'ResultCode' => 0]],
        ])->assertOk()->assertJson(['ResultCode' => 0]);

        $this->postJson('/api/mpesa/callback/test-secret', [])
            ->assertOk()
            ->assertJson(['ResultCode' => 0]);
    }

    public function test_success_without_a_receipt_does_not_grant_access(): void
    {
        $payment = $this->makePayment();

        $this->postJson('/api/mpesa/callback/test-secret', $this->callbackPayload($payment, 20, ''))
            ->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->voucher_id);
        $this->assertSame(0, Voucher::count());
    }

    public function test_callback_requires_the_secret_in_the_path(): void
    {
        $payment = $this->makePayment();

        $this->postJson('/api/mpesa/callback/wrong', $this->callbackPayload($payment, 20, 'RECEIPT-4'))
            ->assertNotFound();

        $this->postJson('/api/mpesa/callback/test-secret', $this->callbackPayload($payment, 20, 'RECEIPT-4'))
            ->assertOk();
    }

    public function test_wrong_amount_fails_the_payment_and_creates_no_voucher(): void
    {
        $payment = $this->makePayment();

        $this->postJson('/api/mpesa/callback/test-secret', $this->callbackPayload($payment, 1, 'RECEIPT-5'))
            ->assertOk();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->voucher_id);
        $this->assertSame(0, Voucher::count());
    }

    public function test_lost_callback_is_recovered_by_the_scheduled_job(): void
    {
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'token'], 200),
            'sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query' => Http::response([
                'ResultCode' => '0',
                'ResultDesc' => 'Success',
                'MpesaReceiptNumber' => 'RECOVERY-1',
            ], 200),
        ]);

        $payment = $this->makePayment();
        $payment->forceFill(['created_at' => now()->subMinutes(2)])->save();

        $this->artisan('payments:recover')->assertExitCode(0);

        $payment->refresh();

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->voucher);
        $this->assertSame('active', $payment->voucher->status);
    }

    public function test_recovery_expires_a_stale_payment_without_a_checkout_id(): void
    {
        $payment = $this->makePayment();
        $payment->forceFill([
            'created_at' => now()->subMinutes(10),
            'checkout_request_id' => null,
        ])->save();

        $this->artisan('payments:recover')->assertExitCode(0);

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame(0, Voucher::count());
    }

    public function test_router_down_keeps_the_code_hidden_until_provisioning_succeeds(): void
    {
        $driver = new class implements RouterDriver
        {
            public bool $up = false;

            public function ensureUser(Voucher $voucher): void
            {
                if (! $this->up) {
                    throw new RuntimeException('Router unreachable');
                }

                DB::table('mock_router_users')->updateOrInsert(
                    ['username' => $voucher->code],
                    ['password' => $voucher->code, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
                );
            }

            public function removeUser(string $username): void
            {
                DB::table('mock_router_users')->where('username', $username)->delete();
            }

            public function userExists(string $username): bool
            {
                return DB::table('mock_router_users')->where('username', $username)->exists();
            }

            public function isReachable(): bool
            {
                return $this->up;
            }
        };

        $this->app->instance(RouterDriver::class, $driver);

        $payment = $this->makePayment();

        $this->postJson('/api/mpesa/callback/test-secret', $this->callbackPayload($payment, 20, 'RECEIPT-6'))
            ->assertOk();

        $payment->refresh();
        $voucher = $payment->voucher;

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertFalse($voucher->mikrotik_synced);

        $this->get($payment->signedStatusUrl())
            ->assertOk()
            ->assertJson(['status' => 'paid', 'provisioned' => false, 'code' => null]);

        $driver->up = true;
        (new SyncVoucherToMikrotik($voucher, 'add'))->handle($driver);

        $this->assertTrue($voucher->fresh()->mikrotik_synced);
        $this->get($payment->signedStatusUrl())
            ->assertOk()
            ->assertJson(['provisioned' => true, 'code' => $voucher->code]);
    }

    public function test_expired_voucher_is_reported_as_terminal_on_the_payment_status_page(): void
    {
        $payment = $this->makePayment();

        $this->postJson('/api/mpesa/callback/test-secret', $this->callbackPayload($payment, 20, 'RECEIPT-EXPIRED'))
            ->assertOk();

        $payment->refresh();
        $payment->voucher->update(['expires_at' => now()->subMinute()]);

        $this->get($payment->signedStatusUrl())
            ->assertOk()
            ->assertJson([
                'status' => 'expired',
                'provisioned' => false,
                'code' => null,
            ]);
    }

    public function test_returning_customer_can_log_in_with_a_valid_code(): void
    {
        $voucher = $this->makeActiveVoucher();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/check', [
                'code' => strtolower($voucher->code),
                'login_url' => 'http://192.168.100.1/login',
                'orig_url' => 'http://example.com/start',
            ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('username='.$voucher->code, $location);
        $this->assertStringContainsString('password='.$voucher->code, $location);
        $this->assertStringContainsString('dst='.urlencode('http://example.com/start'), $location);
    }

    public function test_portal_never_sends_a_code_to_an_untrusted_login_host(): void
    {
        $voucher = $this->makeActiveVoucher();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/check', [
                'code' => $voucher->code,
                'login_url' => 'https://evil.example/login',
                'orig_url' => 'https://evil.example/start',
            ]);

        $response->assertRedirect(route('portal.status', ['voucher' => $voucher->access_token]));

        $location = $response->headers->get('Location');
        $this->assertStringNotContainsString('evil.example', $location);
        $this->assertStringNotContainsString('username=', $location);
    }

    public function test_expired_or_unused_codes_get_a_clear_error(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $expired = $this->makeActiveVoucher();
        $expired->update(['expires_at' => now()->subMinute()]);

        $this->post('/check', ['code' => $expired->code])
            ->assertSessionHasErrors('code');

        $unused = $this->makeVoucher();

        $this->post('/check', ['code' => $unused->code])
            ->assertSessionHasErrors('code');
    }

    public function test_payment_status_requires_a_signed_url(): void
    {
        $payment = $this->makePayment();

        $this->get("/payments/{$payment->id}/status")->assertForbidden();
        $this->get($payment->signedStatusUrl())->assertOk();
    }

    public function test_purchase_creates_a_pending_payment_before_the_stk_push(): void
    {
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'token'], 200),
            'sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
                'MerchantRequestID' => 'merchant-1',
                'CheckoutRequestID' => 'checkout-1',
            ], 200),
        ]);

        $plan = $this->makePlan();

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/purchase', [
                'plan_id' => $plan->id,
                'phone' => '0712 345 678',
                'mac' => 'AA:BB:CC:DD:EE:FF',
                'ip' => '192.168.100.50',
                'login_url' => 'http://192.168.100.1/login',
                'orig_url' => 'http://example.com/start',
            ]);

        $response->assertRedirect();

        $payment = Payment::firstOrFail();

        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame($plan->id, $payment->plan_id);
        $this->assertSame('254712345678', $payment->phone);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $payment->mac);
        $this->assertSame('checkout-1', $payment->checkout_request_id);
        $this->assertNull($payment->voucher_id);
        $this->assertSame(0, Voucher::count());
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

    public function test_voucher_codes_are_eight_unambiguous_characters(): void
    {
        $plan = $this->makePlan();

        for ($i = 0; $i < 30; $i++) {
            $voucher = Voucher::create(['plan_id' => $plan->id]);

            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $voucher->code);
        }
    }

    public function test_expiry_job_marks_expired_and_removes_the_router_user(): void
    {
        $voucher = $this->makeActiveVoucher();
        $driver = app(RouterDriver::class);

        $driver->ensureUser($voucher);

        $this->assertTrue($driver->userExists($voucher->code));

        $voucher->update(['expires_at' => now()->subMinute()]);

        (new ExpireVoucherJob($voucher))->handle($driver);

        $this->assertSame('expired', $voucher->fresh()->status);
        $this->assertFalse($driver->userExists($voucher->code));
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

    public function test_admin_cannot_push_an_unpaid_voucher_to_the_router(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $voucher = $this->makeVoucher();

        $this->actingAs($admin)
            ->post(route('admin.vouchers.sync', $voucher))
            ->assertSessionHas('error');

        $this->assertFalse($voucher->fresh()->mikrotik_synced);
        $this->assertDatabaseMissing('mock_router_users', ['username' => $voucher->code]);
    }

    public function test_voucher_status_is_only_reachable_with_the_access_token(): void
    {
        $voucher = $this->makeVoucher();

        $this->get("/voucher/{$voucher->id}/status")->assertNotFound();
        $this->get("/voucher/{$voucher->access_token}/status")->assertOk();
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
            'data_limit' => '5G',
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

    private function makeActiveVoucher(): Voucher
    {
        $voucher = $this->makeVoucher();
        $voucher->activate();
        $voucher->markProvisioned();

        return $voucher->fresh();
    }

    private function makePayment(): Payment
    {
        $plan = $this->makePlan();

        return Payment::create([
            'plan_id' => $plan->id,
            'phone' => '254712345678',
            'amount' => $plan->price,
            'status' => Payment::STATUS_PENDING,
            'merchant_request_id' => 'merchant-'.uniqid(),
            'checkout_request_id' => 'checkout-'.uniqid(),
        ]);
    }

    private function callbackPayload(Payment $payment, int $amount, string $receipt): array
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
                            ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];
    }
}
