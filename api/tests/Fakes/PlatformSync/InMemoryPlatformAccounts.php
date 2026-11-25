<?php

namespace Tests\Fakes\PlatformSync;

use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformAccount;
use App\PlatformSync\Ports\PlatformAccounts;

class InMemoryPlatformAccounts implements PlatformAccounts
{
    /** @var array<string, PlatformAccount> by "user:platform" */
    public array $accounts = [];

    public function of(int $userId, Platform $platform): ?PlatformAccount
    {
        return $this->accounts["{$userId}:{$platform->value}"] ?? null;
    }

    public function save(PlatformAccount $account): void
    {
        $this->accounts["{$account->userId}:{$account->platform->value}"] = $account;
    }

    public function remove(int $userId, Platform $platform): void
    {
        unset($this->accounts["{$userId}:{$platform->value}"]);
    }
}
