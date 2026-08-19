<?php

namespace Tests;

use App\Contracts\AnalystLlm;
use App\Contracts\DemoStorage;
use App\Contracts\DocRetriever;
use App\Contracts\DocsLlm;
use App\Contracts\EventBus;
use App\Contracts\ParseQueue;
use App\Contracts\RoundRetriever;
use App\Contracts\SearchIndex;
use App\Contracts\SemanticRetriever;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeDemoStorage;
use Tests\Fakes\FakeDocRetriever;
use Tests\Fakes\FakeRoundRetriever;
use Tests\Fakes\FakeSearchIndex;
use Tests\Fakes\FakeSemanticRetriever;
use Tests\Fakes\SpyAnalystLlm;
use Tests\Fakes\SpyDocsLlm;
use Tests\Fakes\SpyEventBus;
use Tests\Fakes\SpyParseQueue;

abstract class TestCase extends BaseTestCase
{
    // Every external system sits behind an interface (house rule), so tests swap all
    // of them for in-memory fakes here — no Redis, MinIO, or Meilisearch is ever hit.
    protected FakeDemoStorage $demoStorage;

    protected SpyParseQueue $parseQueue;

    protected SpyEventBus $eventBus;

    protected SpyAnalystLlm $analystLlm;

    protected FakeSemanticRetriever $semanticRetriever;

    protected FakeRoundRetriever $roundRetriever;

    protected FakeDocRetriever $docRetriever;

    protected SpyDocsLlm $docsLlm;

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
        $this->analystLlm = new SpyAnalystLlm;
        $this->semanticRetriever = new FakeSemanticRetriever;
        $this->roundRetriever = new FakeRoundRetriever;
        $this->docRetriever = new FakeDocRetriever;
        $this->docsLlm = new SpyDocsLlm;

        $this->app->instance(DemoStorage::class, $this->demoStorage);
        $this->app->instance(ParseQueue::class, $this->parseQueue);
        $this->app->instance(EventBus::class, $this->eventBus);
        $this->app->instance(SearchIndex::class, new FakeSearchIndex);
        $this->app->instance(AnalystLlm::class, $this->analystLlm);
        $this->app->instance(SemanticRetriever::class, $this->semanticRetriever);
        $this->app->instance(RoundRetriever::class, $this->roundRetriever);
        $this->app->instance(DocRetriever::class, $this->docRetriever);
        $this->app->instance(DocsLlm::class, $this->docsLlm);
    }
}
