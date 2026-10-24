<?php

namespace App\Console\Commands;

use App\Services\VoucherService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vouchers:expire')]
#[Description('Expire overdue vouchers and retry removing expired/revoked users from MikroTik')]
class ExpireVouchers extends Command
{
    public function handle(VoucherService $service): int
    {
        $count = $service->expireOverdue();
        $this->info("Queued {$count} voucher expiry/removal job(s).");

        return self::SUCCESS;
    }
}
