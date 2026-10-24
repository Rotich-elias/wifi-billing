<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('vouchers:reconcile {--fix : Remove hotspot users that no longer have a valid voucher}')]
#[Description('Compare vouchers in the database with MikroTik hotspot users and report drift')]
class ReconcileVouchers extends Command
{
    public function handle(MikrotikService $mikrotik): int
    {
        try {
            $users = $mikrotik->getHotspotUsers();
        } catch (Throwable $e) {
            $this->error('Could not read hotspot users: '.$e->getMessage());

            return self::FAILURE;
        }

        $stale = [];

        foreach ($users as $user) {
            $code = $user['name'] ?? null;

            if (! $code) {
                continue;
            }

            $voucher = Voucher::withTrashed()->where('code', $code)->first();

            $valid = $voucher
                && ! $voucher->trashed()
                && $voucher->status === 'active'
                && ! $voucher->isExpired();

            if (! $valid) {
                $stale[] = $code;
            }
        }

        $unfulfilled = Payment::where('status', 'completed')
            ->whereHas('voucher', fn ($query) => $query->where('status', 'unused'))
            ->count();

        $this->info('Hotspot users on router: '.count($users));
        $this->info('Router users without a valid voucher: '.count($stale));
        $this->info('Completed payments whose voucher is still unused: '.$unfulfilled);

        if ($stale !== []) {
            $this->line('Stale users: '.implode(', ', array_slice($stale, 0, 50)).(count($stale) > 50 ? ' …' : ''));
        }

        if ($this->option('fix')) {
            $removed = 0;

            foreach ($stale as $code) {
                try {
                    $mikrotik->removeHotspotUserByName($code);
                    $removed++;
                } catch (Throwable $e) {
                    $this->warn("Failed to remove {$code}: {$e->getMessage()}");
                }
            }

            $this->info("Removed {$removed} stale hotspot user(s).");
        } elseif ($stale !== []) {
            $this->warn('Run with --fix to remove stale hotspot users.');
        }

        return self::SUCCESS;
    }
}
