<?php

namespace App\PlatformSync\Domain;

use InvalidArgumentException;

// A match's id on its platform (FACEIT's `1-<uuid>`): the `external_id` a Clutchlab match is filed
// under. Its format is the platform's, checked where it comes in (the route, the adapter).
final readonly class PlatformMatchId
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('A platform match id is never empty.');
        }
    }
}
