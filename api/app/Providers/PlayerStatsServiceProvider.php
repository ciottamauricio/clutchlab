<?php

namespace App\Providers;

use App\PlayerStats\Adapters\Eloquent\EloquentMatches;
use App\PlayerStats\Adapters\Eloquent\EloquentRosters;
use App\PlayerStats\Adapters\Postgres\PostgresMatchEvents;
use App\PlayerStats\Ports\Matches;
use App\PlayerStats\Ports\MatchEvents;
use App\PlayerStats\Ports\Rosters;
use Illuminate\Support\ServiceProvider;

// app/PlayerStats's composition root: the only class outside the module's adapters that names
// them (tests/Architecture rule 5). Its use cases need no binding; the container builds them.
class PlayerStatsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Matches::class, EloquentMatches::class);
        $this->app->bind(Rosters::class, EloquentRosters::class);
        $this->app->bind(MatchEvents::class, fn () => new PostgresMatchEvents($this->app->make('db')->connection()));
    }
}
