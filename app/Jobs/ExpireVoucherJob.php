<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireVoucherJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /** Avoid duplicate expiry/removal jobs from the scheduler and status page. */
    public int $uniqueFor = 300;

    public function __construct(public readonly Voucher $voucher) {}

    public function uniqueId(): string
    {
        return 'expire-voucher:'.$this->voucher->id;
    }

    public function handle(MikrotikService $mikrotik): void
    {
        $voucher = Voucher::withTrashed()->find($this->voucher->id);

        if (! $voucher) {
            return;
        }

        if ($voucher->status === 'active') {
            $voucher->update(['status' => 'expired']);
        }

        // Always remove the hotspot user, even if another code path already
        // flipped the status to expired.
        if ($voucher->mikrotik_synced) {
            $mikrotik->syncVoucher($voucher, 'remove');
        }
    }
}
