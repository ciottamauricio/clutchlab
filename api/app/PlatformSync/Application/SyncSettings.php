<?php

namespace App\PlatformSync\Application;

use App\PlatformSync\Domain\Platform;

// This instance's sync settings for one platform: whether it's on (the instance has a key), and how
// long and how big the panel's list is.
final readonly class SyncSettings
{
    public function __construct(
        public Platform $platform,
        public bool $enabled,
        public int $listTtl,
        public int $listSize,
    ) {}

    public function key(string $what, string $id): string
    {
        return "platform_sync:{$this->platform->value}:{$what}:{$id}";
    }
}
