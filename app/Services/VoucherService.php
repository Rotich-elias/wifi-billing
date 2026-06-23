<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VoucherService
{
    public function generate(Plan $plan, int $quantity = 1): Collection
    {
        $batchId  = Str::uuid()->toString();
        $vouchers = collect();

        for ($i = 0; $i < $quantity; $i++) {
            $vouchers->push(Voucher::create([
                'plan_id'  => $plan->id,
                'batch_id' => $batchId,
            ]));
        }

        return $vouchers;
    }

    public function expireOverdue(): int
    {
        $count = 0;
        Voucher::where('status', 'active')
            ->where('expires_at', '<', now())
            ->each(function (Voucher $voucher) use (&$count) {
                $voucher->update(['status' => 'expired']);
                app(MikrotikService::class)->syncVoucher($voucher, 'remove');
                $count++;
            });

        return $count;
    }

    public function revokeVoucher(Voucher $voucher): void
    {
        $voucher->update(['status' => 'revoked']);

        if ($voucher->mikrotik_synced) {
            app(MikrotikService::class)->syncVoucher($voucher, 'remove');
        }
    }
}
