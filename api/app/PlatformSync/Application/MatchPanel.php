<?php

namespace App\PlatformSync\Application;

use App\PlatformSync\Domain\MatchRow;
use App\PlatformSync\Domain\PlatformAccount;

// The panel: the user's account and their latest finished matches, or why there's no account.
final readonly class MatchPanel
{
    /** @param  list<MatchRow>  $rows */
    public function __construct(
        public ?PlatformAccount $account,
        public ?MissingAccount $reason,
        public array $rows,
    ) {}
}
