<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireVoucherJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly Voucher $voucher) {}

    public function handle(MikrotikService $mikrotik): void
    {
        $this->voucher->update(['status' => 'expired']);

        if ($this->voucher->mikrotik_synced) {
            $mikrotik->syncVoucher($this->voucher, 'remove');
        }
    }
}
