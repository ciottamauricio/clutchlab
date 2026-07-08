<?php

namespace App\Providers;

use App\Contracts\DemoStorage;
use App\Contracts\ParseQueue;
use App\Contracts\SearchIndex;
use App\Models\User;
use App\Queue\RedisParseQueue;
use App\Search\MeilisearchIndex;
use App\Storage\S3DemoStorage;
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
        $this->app->bind(SearchIndex::class, fn () => new MeilisearchIndex(
            new MeilisearchClient(config('clutch.meili.host'), config('clutch.meili.key'))
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The master admin passes every policy check. Returning null (not false) lets
        // non-admins fall through to the ordinary policy.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);
    }
}
