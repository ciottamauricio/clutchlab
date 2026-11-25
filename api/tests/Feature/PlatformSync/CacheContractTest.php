<?php

namespace Tests\Feature\PlatformSync;

use App\PlatformSync\Adapters\Laravel\LaravelCache;
use App\PlatformSync\Ports\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\PlatformSync\InMemoryCache;
use Tests\TestCase;

// The Cache contract on the in-memory adapter (its own clock) and on Laravel's array store (the
// test clock), so a TTL means the same in both.
class CacheContractTest extends TestCase
{
    public static function adapters(): array
    {
        return ['in memory' => ['memory'], 'laravel' => ['laravel']];
    }

    private function adapter(string $kind): Cache
    {
        return $kind === 'laravel' ? $this->app->make(LaravelCache::class) : new InMemoryCache;
    }

    private function elapse(string $kind, Cache $cache, int $seconds): void
    {
        $kind === 'laravel' ? $this->travel($seconds)->seconds() : $cache->advance($seconds);
    }

    #[DataProvider('adapters')]
    public function test_a_missing_key_has_nothing(string $kind): void
    {
        $cache = $this->adapter($kind);

        $this->assertFalse($cache->has('k'));
        $this->assertNull($cache->get('k'));
    }

    #[DataProvider('adapters')]
    public function test_a_value_is_kept_for_its_ttl_and_then_gone(string $kind): void
    {
        $cache = $this->adapter($kind);
        $cache->put('list', [['id' => 'a', 'finished' => true]], 300);
        $cache->put('none', true, 300);

        $this->elapse($kind, $cache, 299);
        $this->assertSame([['id' => 'a', 'finished' => true]], $cache->get('list'));
        $this->assertTrue($cache->has('none'));

        $this->elapse($kind, $cache, 2);
        $this->assertNull($cache->get('list'));
        $this->assertFalse($cache->has('none'));
    }

    #[DataProvider('adapters')]
    public function test_forever_outlasts_any_ttl_until_forgotten(string $kind): void
    {
        $cache = $this->adapter($kind);
        $cache->forever('match', ['id' => 'a', 'map' => 'de_ancient']);

        $this->elapse($kind, $cache, 365 * 24 * 3600);
        $this->assertSame(['id' => 'a', 'map' => 'de_ancient'], $cache->get('match'));

        $cache->forget('match');
        $this->assertNull($cache->get('match'));
    }
}
