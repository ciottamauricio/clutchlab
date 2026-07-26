<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Authorization\PermissionCatalog;
use App\Contracts\AnalystLlm;
use App\Contracts\DemoStorage;
use App\Contracts\EmbeddingClient;
use App\Contracts\EventBus;
use App\Contracts\EventSubscriber;
use App\Contracts\ParseQueue;
use App\Contracts\PermissionService;
use App\Contracts\SearchIndex;
use App\Contracts\SemanticRetriever;
use App\Events\Subscribers\EmailTrainingRoster;
use App\Events\Subscribers\EventHandler;
use App\Llm\AnthropicAnalyst;
use App\Llm\HashEmbeddings;
use App\Models\User;
use App\Queue\RedisEventBus;
use App\Queue\RedisEventSubscriber;
use App\Queue\RedisParseQueue;
use App\Search\MeilisearchIndex;
use App\Search\PgVectorRetriever;
use App\Services\DbPermissionService;
use App\Storage\S3DemoStorage;
use App\Telemetry\Tracing;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client as MeilisearchClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DemoStorage::class, fn () => new S3DemoStorage(Storage::disk('s3')));
        $this->app->bind(ParseQueue::class, RedisParseQueue::class);
        $this->app->bind(EventBus::class, RedisEventBus::class);
        $this->app->bind(EventSubscriber::class, RedisEventSubscriber::class);

        // Handlers for incoming cross-service facts (events:listen). Registering a new
        // reaction is one line here — the listener routes by each handler's handles().
        $this->app->tag([EmailTrainingRoster::class], EventHandler::class);
        $this->app->bind(SearchIndex::class, fn () => new MeilisearchIndex(
            new MeilisearchClient(config('clutch.meili.host'), config('clutch.meili.key'))
        ));
        // The analyst generator, chosen by config. 'claude' is wired; add an 'ollama' or
        // 'llphant' case here — the AnalystLlm contract is all AskAnalystAction depends on.
        $this->app->bind(AnalystLlm::class, fn () => match (config('clutch.analyst_provider')) {
            default => new AnthropicAnalyst(
                new AnthropicClient(apiKey: config('clutch.anthropic.key')),
                config('clutch.anthropic.model'),
            ),
        });

        // Semantic retrieval: an embedder feeding a pgvector store, both seams. Only 'hash'
        // (keyless local stand-in) is wired; add a 'voyage'/'ollama' case here once you've
        // written the class. Its dimensions() must equal the migrated column width — pinned
        // by EMBED_DIMENSIONS so the two can't silently disagree.
        $this->app->bind(EmbeddingClient::class, fn () => match (config('clutch.embed.provider')) {
            default => new HashEmbeddings((int) config('clutch.embed.dimensions')),
        });
        $this->app->bind(SemanticRetriever::class, PgVectorRetriever::class);

        // Singleton so the per-request grant cache is shared across every check in a request.
        $this->app->singleton(PermissionService::class, DbPermissionService::class);

        // One tracer per process, exporting to the same OTLP endpoint the Go services use.
        $this->app->singleton(Tracing::class, fn () => new Tracing(
            config('clutch.otel.endpoint'),
            config('clutch.otel.service'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The master admin passes every policy check. Returning null (not false) lets
        // non-admins fall through to the ordinary policy. `rsvp` is exempt: answering an
        // invite is a self-only act — there's no invite to answer if you're not on the
        // roster, so admin power doesn't apply. It falls through to the policy for everyone.
        Gate::before(fn (User $user, string $ability) => $user->isAdmin() && $ability !== 'rsvp' ? true : null);

        // Named ability for admin-only routes (`->middleware('can:admin')`). Non-admins fall
        // here from Gate::before and are denied; admins never reach it (short-circuited above).
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // App-scope abilities become named gates (`->middleware('can:awards.view')`), each
        // resolved from the live grants for the user's global role.
        $perms = $this->app->make(PermissionService::class);

        foreach (PermissionCatalog::abilities() as $key => [$scope]) {
            if ($scope === PermissionCatalog::APP) {
                Gate::define($key, fn (User $user) => $perms->canApp($user, $key));
            }
        }

        // Flush batched spans after the response is sent — a short-lived request still
        // ships its trace. Resolved lazily so a request that never traced pays nothing.
        $this->app->terminating(function () {
            if ($this->app->resolved(Tracing::class)) {
                $this->app->make(Tracing::class)->shutdown();
            }
        });
    }
}
