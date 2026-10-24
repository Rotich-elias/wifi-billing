<?php

namespace App\Console\Commands;

use App\Services\VoucherService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vouchers:expire')]
#[Description('Mark overdue active vouchers as expired and remove from MikroTik')]
class ExpireVouchers extends Command
{
    public function handle(VoucherService $service): int
    {
        $count = $service->expireOverdue();
        $this->info("Expired {$count} voucher(s).");

        return self::SUCCESS;
    }
}
