<?php

namespace App\Services;

use App\Jobs\ExpireVoucherJob;
use App\Jobs\SyncVoucherToMikrotik;
use App\Models\Plan;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VoucherService
{
    public function generate(Plan $plan, int $quantity = 1): Collection
    {
        $batchId = Str::uuid()->toString();
        $vouchers = collect();

        for ($i = 0; $i < $quantity; $i++) {
            $attempts = 0;

            while (true) {
                try {
                    $vouchers->push(Voucher::create([
                        'plan_id' => $plan->id,
                        'batch_id' => $batchId,
                    ]));

                    break;
                } catch (QueryException $e) {
                    // Extremely unlikely code collision: retry with a new code.
                    if (++$attempts >= 5 || ! str_contains($e->getMessage(), 'UNIQUE')) {
                        throw $e;
                    }
                }
            }
        }

        return $vouchers;
    }

    public function expireOverdue(): int
    {
        $count = 0;
        Voucher::where('status', 'active')
            ->where('expires_at', '<', now())
            ->each(function (Voucher $voucher) use (&$count) {
                ExpireVoucherJob::dispatch($voucher);
                $count++;
            });

        return $count;
    }

    public function revokeVoucher(Voucher $voucher): void
    {
        $voucher->update(['status' => 'revoked']);

        if ($voucher->mikrotik_synced) {
            SyncVoucherToMikrotik::dispatch($voucher, 'remove');
        }
    }
}
