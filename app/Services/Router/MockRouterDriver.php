<?php

namespace App\Services\Router;

use App\Contracts\RouterDriver;
use App\Models\MikrotikSyncLog;
use App\Models\Plan;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class MockRouterDriver implements RouterDriver
{
    public function ensureUser(Voucher $voucher): void
    {
        $plan = $voucher->plan;

        DB::table('mock_router_users')->updateOrInsert(
            ['username' => $voucher->code],
            [
                'password' => $voucher->code,
                'profile' => 'voucher_plan_'.$plan->id,
                'uptime_limit' => $this->uptimeLimit($plan),
                'data_limit' => $plan->data_limit,
                'comment' => 'voucher_id:'.$voucher->id,
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        MikrotikSyncLog::create([
            'voucher_id' => $voucher->id,
            'action' => 'add',
            'status' => 'success',
            'message' => 'Mock router user ensured',
        ]);
    }

    public function removeUser(string $username): void
    {
        DB::table('mock_router_users')->where('username', $username)->delete();

        MikrotikSyncLog::create([
            'voucher_id' => Voucher::withTrashed()->where('code', $username)->value('id'),
            'action' => 'remove',
            'status' => 'success',
            'message' => 'Mock router user removed',
        ]);
    }

    public function userExists(string $username): bool
    {
        return DB::table('mock_router_users')->where('username', $username)->exists();
    }

    public function isReachable(): bool
    {
        return true;
    }

    private function uptimeLimit(Plan $plan): string
    {
        $seconds = $plan->session_timeout ?: ((int) $plan->duration_hours * 3600);

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60,
        );
    }
}
