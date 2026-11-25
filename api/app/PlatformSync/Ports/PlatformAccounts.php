<?php

namespace App\PlatformSync\Ports;

use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformAccount;

// The users' accounts on each platform, as Clutchlab remembers them.
interface PlatformAccounts
{
    public function of(int $userId, Platform $platform): ?PlatformAccount;

    // Replaces any account the user had on that platform.
    public function save(PlatformAccount $account): void;

    public function remove(int $userId, Platform $platform): void;
}
