<?php

namespace App\PlatformSync\Domain;

use InvalidArgumentException;

// The Steam ID on a Clutchlab profile: how a player is found on a platform.
final readonly class SteamId
{
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('A Steam ID is never empty.');
        }
    }

    public static function tryFrom(?string $value): ?self
    {
        return $value === null || $value === '' ? null : new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
