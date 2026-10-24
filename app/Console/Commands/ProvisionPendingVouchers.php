<?php

namespace App\Console\Commands;

use App\Jobs\SyncVoucherToMikrotik;
use App\Models\Voucher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('vouchers:provision-pending')]
#[Description('Re-queue active vouchers that are not yet present on the router')]
class ProvisionPendingVouchers extends Command
{
    public function handle(): int
    {
        $vouchers = Voucher::where('status', 'active')
            ->where('mikrotik_synced', false)
            ->where('activated_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->limit(100)
            ->get();

        $queued = 0;

        foreach ($vouchers as $voucher) {
            try {
                SyncVoucherToMikrotik::dispatch($voucher, 'add');
                $queued++;
            } catch (Throwable $e) {
                Log::error('Could not queue pending voucher provisioning', [
                    'voucher_id' => $voucher->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Queued {$queued} voucher(s) for provisioning.");

        return self::SUCCESS;
    }
}
