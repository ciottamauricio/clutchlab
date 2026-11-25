<?php

namespace Tests\Fakes\PlatformSync;

use App\PlatformSync\Ports\Cache;
use LogicException;

// The cache in memory, on a clock the test moves. A value is kept as the database and Redis stores
// would give it back, unserialized with no classes allowed, and refused if it wouldn't survive
// that: the tests' array store never serializes, so only here does an object put into the cache
// fail the way it would in dev.
class InMemoryCache implements Cache
{
    private int $now = 0;

    /** @var array<string, array{value: string, expires: ?int}> */
    private array $items = [];

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function get(string $key): array|bool|null
    {
        $item = $this->items[$key] ?? null;
        if ($item === null || ($item['expires'] !== null && $item['expires'] <= $this->now)) {
            return null;
        }

        return unserialize($item['value'], ['allowed_classes' => false]);
    }

    public function put(string $key, array|bool $value, int $seconds): void
    {
        $this->store($key, $value, $this->now + $seconds);
    }

    public function forever(string $key, array|bool $value): void
    {
        $this->store($key, $value, null);
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }

    private function store(string $key, array|bool $value, ?int $expires): void
    {
        $stored = serialize($value);
        if (unserialize($stored, ['allowed_classes' => false]) !== $value) {
            throw new LogicException("The cache keeps plain values only, and {$key} holds an object.");
        }
        $this->items[$key] = ['value' => $stored, 'expires' => $expires];
    }
}
