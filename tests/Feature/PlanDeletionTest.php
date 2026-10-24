<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plan_with_vouchers_can_be_deleted_without_losing_them(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $plan = $this->makePlan();
        $voucher = Voucher::create(['plan_id' => $plan->id, 'status' => 'unused']);
        $payment = Payment::create([
            'plan_id' => $plan->id,
            'voucher_id' => $voucher->id,
            'phone' => '254712345678',
            'amount' => $plan->price,
            'status' => Payment::STATUS_PAID,
            'mpesa_receipt' => 'TESTRECEIPT1',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);

        // Voucher and payment pages still resolve the archived plan.
        $this->assertSame($plan->name, $voucher->fresh()->plan->name);
        $this->assertSame($plan->name, $payment->fresh()->plan->name);

        $this->actingAs($admin)
            ->get(route('admin.vouchers.show', $voucher))
            ->assertOk()
            ->assertSee($plan->name);
    }

    public function test_a_deleted_plan_disappears_from_the_admin_and_portal_lists(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $plan = $this->makePlan(['name' => 'Archive Me']);
        $plan->delete();

        $this->actingAs($admin)
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertDontSee('Archive Me');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Archive Me');
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Delete Me',
            'type' => 'daily',
            'duration_hours' => 1,
            'price' => 10,
            'bandwidth_up' => '1M',
            'bandwidth_down' => '1M',
            'is_active' => true,
        ], $overrides));
    }
}
