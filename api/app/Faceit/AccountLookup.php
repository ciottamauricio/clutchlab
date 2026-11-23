<?php

namespace App\Faceit;

use App\Models\FaceitAccount;

// What finding a user's FACEIT account came to: the account, or why there is none
// (faceit.no_steam_id, faceit.not_found) — the user's situation, not a failure.
final readonly class AccountLookup
{
    private function __construct(
        public ?FaceitAccount $account,
        public ?string $reason,
    ) {}

    public static function found(FaceitAccount $account): self
    {
        return new self($account, null);
    }

    public static function missing(string $reason): self
    {
        return new self(null, $reason);
    }
}
