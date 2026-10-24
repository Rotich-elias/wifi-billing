<?php

namespace App\Services\Router;

use App\Contracts\RouterDriver;
use App\Models\Voucher;
use App\Services\MikrotikService;
use Throwable;

class RouterOsApiDriver implements RouterDriver
{
    public function __construct(private MikrotikService $mikrotik) {}

    public function ensureUser(Voucher $voucher): void
    {
        $this->mikrotik->connect();

        try {
            if ($this->mikrotik->getHotspotUser($voucher->code)) {
                return;
            }

            $this->mikrotik->addHotspotUser($voucher);
        } finally {
            $this->mikrotik->disconnect();
        }
    }

    public function removeUser(string $username): void
    {
        $this->mikrotik->connect();

        try {
            if (! $this->mikrotik->getHotspotUser($username)) {
                return;
            }

            $this->mikrotik->removeHotspotUser($username);
        } finally {
            $this->mikrotik->disconnect();
        }
    }

    public function userExists(string $username): bool
    {
        $this->mikrotik->connect();

        try {
            return $this->mikrotik->getHotspotUser($username) !== null;
        } finally {
            $this->mikrotik->disconnect();
        }
    }

    public function isReachable(): bool
    {
        try {
            $this->mikrotik->connect();

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            $this->mikrotik->disconnect();
        }
    }
}
