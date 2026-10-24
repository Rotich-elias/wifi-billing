<?php

namespace App\Contracts;

use App\Models\Voucher;

interface RouterDriver
{
    /**
     * Create the hotspot user for a voucher. Must be idempotent: if the
     * user already exists, treat it as success.
     */
    public function ensureUser(Voucher $voucher): void;

    /**
     * Remove a hotspot user. Removing a user that does not exist is a no-op.
     */
    public function removeUser(string $username): void;

    public function userExists(string $username): bool;

    public function isReachable(): bool;
}
