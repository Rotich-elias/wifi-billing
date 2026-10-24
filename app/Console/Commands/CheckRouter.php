<?php

namespace App\Console\Commands;

use App\Contracts\RouterDriver;
use App\Jobs\ExpireVoucherJob;
use App\Models\Plan;
use App\Models\Voucher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('router:check {--plan= : Plan ID to use for the test profile} {--keep : Keep the test hotspot user} {--expiry : Also test that expiry removes the user}')]
#[Description('Exercise the configured router driver: connect, provision, re-check and remove a test user')]
class CheckRouter extends Command
{
    public function handle(RouterDriver $driver): int
    {
        if ($this->option('expiry') && $this->option('keep')) {
            $this->error('Use either --keep or --expiry, not both.');

            return self::FAILURE;
        }

        $this->line('Driver: '.config('router.driver'));
        $this->line('Host:   '.config('router.host').':'.config('router.port'));

        if (! $driver->isReachable()) {
            $this->error('Router is not reachable. Check ROUTER_HOST/PORT and the API service.');

            return self::FAILURE;
        }

        $this->info('Router connection OK.');

        $plan = $this->option('plan')
            ? Plan::find((int) $this->option('plan'))
            : Plan::where('is_active', true)->orderBy('id')->first();

        if (! $plan) {
            $this->error('No plan found. Create a plan or pass --plan=ID.');

            return self::FAILURE;
        }

        $this->line("Plan:   #{$plan->id} {$plan->name} ({$plan->duration_label}, data limit: ".($plan->data_limit ?: 'unlimited').')');

        $code = Voucher::generateUniqueCode();
        $voucher = new Voucher(['code' => $code]);
        $voucher->setRelation('plan', $plan);

        try {
            $this->line("Creating hotspot user {$code}...");
            $driver->ensureUser($voucher);

            if (! $driver->userExists($code)) {
                $this->error('User was created but could not be read back.');

                return self::FAILURE;
            }

            $this->info('User created and read back OK.');

            // Provisioning must be safe to retry.
            $driver->ensureUser($voucher);
            $this->info('Idempotent re-run OK (no duplicate user).');

            if ($this->option('expiry')) {
                if (! $this->expiryTest($driver, $plan, $code)) {
                    return self::FAILURE;
                }
            }
        } catch (Throwable $e) {
            $this->error('Router test failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            try {
                if (! $this->option('keep') && $driver->userExists($code)) {
                    $driver->removeUser($code);
                    $this->line('Test user removed.');
                } elseif ($this->option('keep')) {
                    $this->warn("Kept test user {$code}; remove it from the router when finished.");
                }
            } catch (Throwable $e) {
                $this->warn("Could not clean up test user {$code}: {$e->getMessage()}");
            }
        }

        $this->info('Router driver check passed.');

        return self::SUCCESS;
    }

    private function expiryTest(RouterDriver $driver, Plan $plan, string $code): bool
    {
        $voucher = Voucher::create([
            'code' => $code,
            'plan_id' => $plan->id,
            'status' => 'active',
            'activated_at' => now()->subHours(2),
            'expires_at' => now()->subMinute(),
            'mikrotik_synced' => true,
            'mikrotik_user' => $code,
        ]);

        try {
            (new ExpireVoucherJob($voucher))->handle($driver);

            if ($driver->userExists($code)) {
                $this->error('Expiry job did not remove the router user.');

                return false;
            }

            if ($voucher->fresh()->status !== 'expired') {
                $this->error('Expiry job did not mark the voucher expired.');

                return false;
            }

            $this->info('Expiry removed the router user and marked the voucher expired.');

            return true;
        } finally {
            $voucher->forceDelete();
        }
    }
}
