<?php

namespace Tests\Unit\PlatformSync;

use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Fakes\PlatformSync\InMemoryCache;

// The fake's one rule the real stores enforce only in dev: an object put into the cache fails.
class InMemoryCacheTest extends TestCase
{
    public function test_an_object_put_into_the_cache_fails_as_it_would_in_dev(): void
    {
        $this->expectException(LogicException::class);
        (new InMemoryCache)->put('k', ['row' => new stdClass], 60);
    }
}
