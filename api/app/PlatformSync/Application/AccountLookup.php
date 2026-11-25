<?php

namespace App\PlatformSync\Application;

use App\PlatformSync\Domain\PlatformAccount;

// What finding a user's account came to: the account, or why there is none.
final readonly class AccountLookup
{
    private function __construct(
        public ?PlatformAccount $account,
        public ?MissingAccount $reason,
    ) {}

    public static function found(PlatformAccount $account): self
    {
        return new self($account, null);
    }

    public static function missing(MissingAccount $reason): self
    {
        return new self(null, $reason);
    }
}
