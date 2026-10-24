<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncVoucherToMikrotik implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly Voucher $voucher,
        public readonly string $action = 'add',
    ) {}

    public function handle(MikrotikService $mikrotik): void
    {
        $mikrotik->syncVoucher($this->voucher, $this->action);
    }
}
