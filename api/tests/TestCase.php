<?php

namespace Tests;

use App\Contracts\DemoStorage;
use App\Contracts\EventBus;
use App\Contracts\ParseQueue;
use App\Contracts\SearchIndex;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeDemoStorage;
use Tests\Fakes\FakeSearchIndex;
use Tests\Fakes\SpyEventBus;
use Tests\Fakes\SpyParseQueue;

abstract class TestCase extends BaseTestCase
{
    // Every external system sits behind an interface (house rule), so tests swap all
    // of them for in-memory fakes here — no Redis, MinIO, or Meilisearch is ever hit.
    protected FakeDemoStorage $demoStorage;

    protected SpyParseQueue $parseQueue;

    protected SpyEventBus $eventBus;

    protected function setUp(): void
    {
        parent::setUp();

        // Tripwire: if env plumbing ever leaks the container's pgsql config into the
        // test process again (see phpunit.xml), stop before any test can touch it.
        if (config('database.default') !== 'sqlite') {
            self::fail('Tests resolved "'.config('database.default').'" instead of sqlite — refusing to run against a real database.');
        }

        $this->demoStorage = new FakeDemoStorage;
        $this->parseQueue = new SpyParseQueue;
        $this->eventBus = new SpyEventBus;

        $this->app->instance(DemoStorage::class, $this->demoStorage);
        $this->app->instance(ParseQueue::class, $this->parseQueue);
        $this->app->instance(EventBus::class, $this->eventBus);
        $this->app->instance(SearchIndex::class, new FakeSearchIndex);
    }
}
