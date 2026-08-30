<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Authorization\PermissionCatalog;
use App\Contracts\AnalystLlm;
use App\Contracts\DemoStorage;
use App\Contracts\DeploymentHistory;
use App\Contracts\DocRetriever;
use App\Contracts\DocsLlm;
use App\Contracts\EmbeddingClient;
use App\Contracts\EventBus;
use App\Contracts\EventSubscriber;
use App\Contracts\ParseQueue;
use App\Contracts\PermissionService;
use App\Contracts\RoundRetriever;
use App\Contracts\SearchIndex;
use App\Contracts\SemanticRetriever;
use App\Events\Subscribers\ApplyMatchFailed;
use App\Events\Subscribers\ApplyMatchParsed;
use App\Events\Subscribers\EmailTrainingRoster;
use App\Events\Subscribers\EmbedParsedMatch;
use App\Events\Subscribers\EventHandler;
use App\Events\Subscribers\RecordParseFailed;
use App\Events\Subscribers\RecordParseSucceeded;
use App\Llm\AnthropicAnalyst;
use App\Llm\AnthropicDocsExplainer;
use App\Llm\HashEmbeddings;
use App\Llm\OllamaAnalyst;
use App\Llm\OllamaDocsExplainer;
use App\Llm\OllamaEmbeddings;
use App\Models\User;
use App\Queue\RedisEventBus;
use App\Queue\RedisEventSubscriber;
use App\Queue\RedisParseQueue;
use App\Search\MeilisearchIndex;
use App\Search\PgVectorDocRetriever;
use App\Search\PgVectorRetriever;
use App\Search\PgVectorRoundRetriever;
use App\Services\DbPermissionService;
use App\Services\Dora\GithubDeploymentHistory;
use App\Services\Dora\MetricsCalculator;
use App\Storage\S3DemoStorage;
use App\Telemetry\Tracing;
use Illuminate\Http\Client\Factory as Http;
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
        // Order matters within one event: EmbedParsedMatch builds its card from the row
        // ApplyMatchParsed writes, so it is listed after it (and re-checks the status
        // itself, rather than trusting this array to stay in order).
        // Telemetry is tagged last on purpose: recording a metric must never sit in front
        // of the write that makes the match visible to its owner.
        $this->app->tag([
            EmailTrainingRoster::class,
            ApplyMatchParsed::class,
            EmbedParsedMatch::class,
            ApplyMatchFailed::class,
            RecordParseSucceeded::class,
            RecordParseFailed::class,
        ], EventHandler::class);
        $this->app->bind(SearchIndex::class, fn () => new MeilisearchIndex(
            new MeilisearchClient(config('clutch.meili.host'), config('clutch.meili.key'))
        ));
        // The analyst generator, chosen by config. 'claude' is wired; add an 'ollama' or
        // 'llphant' case here — the AnalystLlm contract is all AskAnalystAction depends on.
        $this->app->bind(AnalystLlm::class, fn () => match (config('clutch.analyst_provider')) {
            'ollama' => new OllamaAnalyst(
                $this->app->make(Http::class),
                config('clutch.ollama.host'),
                config('clutch.ollama.model'),
                (int) config('clutch.ollama.timeout'),
            ),
            default => new AnthropicAnalyst(
                new AnthropicClient(apiKey: config('clutch.anthropic.key')),
                config('clutch.anthropic.model'),
            ),
        });

        // Semantic retrieval: an embedder feeding a pgvector store, both seams. 'hash' is
        // the keyless local stand-in; 'ollama' is a learned model served by the ollama
        // container. Whichever is bound, its dimensions() must equal the migrated column
        // width — pinned by EMBED_DIMENSIONS so the two can't silently disagree.
        $this->app->bind(EmbeddingClient::class, fn () => match (config('clutch.embed.provider')) {
            'ollama' => new OllamaEmbeddings(
                $this->app->make(Http::class),
                config('clutch.embed.ollama.host'),
                config('clutch.embed.ollama.model'),
                (int) config('clutch.embed.dimensions'),
                (int) config('clutch.embed.ollama.timeout'),
            ),
            default => new HashEmbeddings((int) config('clutch.embed.dimensions')),
        });
        // The docs explainer, chosen by the SAME config as the analyst: one switch for
        // "local or hosted", not two, so a study of the two providers can't drift into
        // comparing a local answer against a hosted one. Different contract, different
        // prompt — see DocsLlm for why this isn't a flag on AnalystLlm.
        $this->app->bind(DocsLlm::class, fn () => match (config('clutch.analyst_provider')) {
            'ollama' => new OllamaDocsExplainer(
                $this->app->make(Http::class),
                config('clutch.ollama.host'),
                config('clutch.ollama.model'),
                (int) config('clutch.ollama.timeout'),
            ),
            default => new AnthropicDocsExplainer(
                new AnthropicClient(apiKey: config('clutch.anthropic.key')),
                config('clutch.anthropic.model'),
            ),
        });

        // The SLO budget is configuration, not a constant in the calculator: what counts
        // as "delivered fast enough" is a product decision, and the metric has to move
        // when that decision does.
        $this->app->bind(DeploymentHistory::class, fn () => new GithubDeploymentHistory(
            $this->app->make(Http::class),
            config('clutch.dora.github.repo'),
            config('clutch.dora.github.token'),
            config('clutch.dora.github.api'),
            config('clutch.dora.deploy_workflows'),
        ));

        $this->app->bind(MetricsCalculator::class, fn () => new MetricsCalculator(
            (int) config('clutch.dora.parse_slo_ms'),
            (float) config('clutch.dora.parse_slo_target'),
        ));

        $this->app->bind(SemanticRetriever::class, PgVectorRetriever::class);
        $this->app->bind(RoundRetriever::class, PgVectorRoundRetriever::class);
        $this->app->bind(DocRetriever::class, PgVectorDocRetriever::class);

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
