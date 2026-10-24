<?php

namespace App\Jobs;

use App\Contracts\RouterDriver;
use App\Models\MikrotikSyncLog;
use App\Models\Voucher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncVoucherToMikrotik implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public int $uniqueFor = 300;

    public function __construct(
        public readonly Voucher $voucher,
        public readonly string $action = 'add',
    ) {}

    public function uniqueId(): string
    {
        return 'voucher-router:'.$this->voucher->id.':'.$this->action;
    }

    public function handle(RouterDriver $router): void
    {
        $voucher = Voucher::withTrashed()->find($this->voucher->id);

        if (! $voucher) {
            return;
        }

        try {
            if ($this->action === 'remove') {
                $router->removeUser($voucher->code);
                $voucher->update([
                    'mikrotik_synced' => false,
                    'mikrotik_user' => null,
                    'provisioning_error' => null,
                ]);

                return;
            }

            $router->ensureUser($voucher);
            $voucher->markProvisioned($voucher->code);
        } catch (Throwable $e) {
            $voucher->markProvisioningFailed($e->getMessage());

            MikrotikSyncLog::create([
                'voucher_id' => $voucher->id,
                'action' => $this->action,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);

            // Rethrow so the queue retries with backoff.
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $voucher = Voucher::withTrashed()->find($this->voucher->id);

        if ($voucher && $exception) {
            $voucher->markProvisioningFailed($exception->getMessage());
        }
    }
}
