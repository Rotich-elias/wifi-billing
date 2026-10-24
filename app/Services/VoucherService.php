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
    /**
     * Generate vouchers. Printed vouchers stay unused until a customer pays;
     * support vouchers can be activated immediately.
     */
    public function generate(Plan $plan, int $quantity = 1, bool $activate = false): Collection
    {
        $batchId = Str::uuid()->toString();
        $vouchers = collect();

        for ($i = 0; $i < $quantity; $i++) {
            $attempts = 0;

            while (true) {
                try {
                    $voucher = Voucher::create([
                        'plan_id' => $plan->id,
                        'batch_id' => $batchId,
                    ]);

                    if ($activate) {
                        $voucher->activate();
                        SyncVoucherToMikrotik::dispatch($voucher, 'add');
                    }

                    $vouchers->push($voucher);

                    break;
                } catch (QueryException $e) {
                    // Extremely unlikely code collision: retry with a new code.
                    $message = strtolower($e->getMessage());

                    if (++$attempts >= 5 || (! str_contains($message, 'unique') && ! str_contains($message, 'duplicate'))) {
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

        Voucher::where(function ($query) {
            $query->where(function ($query) {
                $query->where('status', 'active')->where('expires_at', '<', now());
            })->orWhere(function ($query) {
                // Retry router removal if an earlier expiry/revoke job ran out
                // of retries. Otherwise the router keeps accepting the code.
                $query->whereIn('status', ['expired', 'revoked'])
                    ->where('mikrotik_synced', true);
            });
        })->each(function (Voucher $voucher) use (&$count) {
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
