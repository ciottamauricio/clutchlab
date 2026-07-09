<?php

namespace App\Providers;

use App\Authorization\PermissionCatalog;
use App\Contracts\DemoStorage;
use App\Contracts\ParseQueue;
use App\Contracts\PermissionService;
use App\Contracts\SearchIndex;
use App\Models\User;
use App\Queue\RedisParseQueue;
use App\Search\MeilisearchIndex;
use App\Services\DbPermissionService;
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

        // Singleton so the per-request grant cache is shared across every check in a request.
        $this->app->singleton(PermissionService::class, DbPermissionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The master admin passes every policy check. Returning null (not false) lets
        // non-admins fall through to the ordinary policy.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

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
    }
}
