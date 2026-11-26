<?php

namespace Tests\Postgres;

use App\Contracts\AnalystLlm;
use App\Contracts\DemoStorage;
use App\Contracts\DemoUnpacker;
use App\Contracts\DocRetriever;
use App\Contracts\DocsLlm;
use App\Contracts\EventBus;
use App\Contracts\ParseQueue;
use App\Contracts\RoundRetriever;
use App\Contracts\SearchIndex;
use App\Contracts\SemanticRetriever;
use App\PlatformSync\Ports\PlatformMatches;
use App\PlatformSync\Ports\PlatformPlayers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeDemoStorage;
use Tests\Fakes\FakeDemoUnpacker;
use Tests\Fakes\FakeDocRetriever;
use Tests\Fakes\FakeRoundRetriever;
use Tests\Fakes\FakeSearchIndex;
use Tests\Fakes\FakeSemanticRetriever;
use Tests\Fakes\PlatformSync\FakePlatform;
use Tests\Fakes\SpyAnalystLlm;
use Tests\Fakes\SpyDocsLlm;
use Tests\Fakes\SpyEventBus;
use Tests\Fakes\SpyParseQueue;

// The base for tests whose SQL needs Postgres (phpunit.pgsql.xml). Not Tests\TestCase: its tripwire
// refuses anything but sqlite, and must keep doing so. External systems are faked the same way.
abstract class PostgresTestCase extends BaseTestCase
{
    use RefreshDatabase;

    public const DATABASE = 'clutchlab_test';

    // setUpTraits() is what runs RefreshDatabase's migrate:fresh, so the guard goes before it. It
    // asks the server it reached, not the config it read: config is what leaked before.
    protected function setUpTraits()
    {
        $this->refuseUnlessThrowawayServer();

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(DemoStorage::class, new FakeDemoStorage);
        $this->app->instance(DemoUnpacker::class, new FakeDemoUnpacker);
        $faceit = new FakePlatform;
        $this->app->instance(PlatformPlayers::class, $faceit);
        $this->app->instance(PlatformMatches::class, $faceit);
        config(['clutch.faceit.key' => 'test-key-not-real']);
        $this->app->instance(ParseQueue::class, new SpyParseQueue);
        $this->app->instance(EventBus::class, new SpyEventBus);
        $this->app->instance(SearchIndex::class, new FakeSearchIndex);
        $this->app->instance(AnalystLlm::class, new SpyAnalystLlm);
        $this->app->instance(SemanticRetriever::class, new FakeSemanticRetriever);
        $this->app->instance(RoundRetriever::class, new FakeRoundRetriever);
        $this->app->instance(DocRetriever::class, new FakeDocRetriever);
        $this->app->instance(DocsLlm::class, new SpyDocsLlm);
    }

    private function refuseUnlessThrowawayServer(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql') {
            self::fail('The Postgres suite resolved "'.$connection->getDriverName().'" — refusing to run.');
        }

        $database = $connection->selectOne('select current_database() as name')->name;
        if ($database !== self::DATABASE) {
            self::fail('The Postgres suite reached database "'.$database.'", not '.self::DATABASE.' — refusing to migrate it.');
        }

        // The throwaway server holds only the test database; the dev server holds clutchlab.
        $dev = $connection->selectOne("select count(*) as n from pg_database where datname = 'clutchlab'")->n;
        if ((int) $dev > 0) {
            self::fail('The Postgres suite reached a server that holds the clutchlab database — refusing to migrate anything on it.');
        }
    }
}
