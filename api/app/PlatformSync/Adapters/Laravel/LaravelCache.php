<?php

namespace App\PlatformSync\Adapters\Laravel;

use App\PlatformSync\Ports\Cache;
use Illuminate\Contracts\Cache\Repository;

// The module's cache on Laravel's default store (the database in dev, the array store in tests).
class LaravelCache implements Cache
{
    public function __construct(private Repository $cache) {}

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    public function get(string $key): array|bool|null
    {
        $value = $this->cache->get($key);

        return is_array($value) || is_bool($value) ? $value : null;
    }

    public function put(string $key, array|bool $value, int $seconds): void
    {
        $this->cache->put($key, $value, $seconds);
    }

    public function forever(string $key, array|bool $value): void
    {
        $this->cache->forever($key, $value);
    }

    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }
}
