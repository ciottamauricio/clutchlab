<?php

namespace Tests\Postgres;

use Illuminate\Support\Facades\DB;

// The test server is built like dev: the same migrations give the worker's tables their own
// schema, resolved through search_path, so the SQL under test sees what production sees.
class ThrowawayServerTest extends PostgresTestCase
{
    public function test_the_analytics_tables_live_in_their_own_schema(): void
    {
        $tables = collect(DB::select("select table_name from information_schema.tables where table_schema = 'analytics' order by 1"))
            ->pluck('table_name')->all();

        $this->assertSame(['kill_events', 'match_player_stats', 'round_events'], $tables);
    }

    public function test_unqualified_names_reach_both_schemas(): void
    {
        $this->assertSame('public, analytics', DB::selectOne('show search_path')->search_path);
        $this->assertSame(0, DB::table('kill_events')->count());
        $this->assertSame(0, DB::table('matches')->count());
    }
}
