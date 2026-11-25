<?php

namespace App\PlatformSync\Ports;

// Rebuildable copies of what the platform said, kept for a while. Plain arrays and booleans only:
// the database and Redis stores unserialize no objects (config/cache.php).
interface Cache
{
    public function has(string $key): bool;

    public function get(string $key): array|bool|null;

    public function put(string $key, array|bool $value, int $seconds): void;

    public function forever(string $key, array|bool $value): void;

    public function forget(string $key): void;
}
