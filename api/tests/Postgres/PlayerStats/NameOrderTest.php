<?php

namespace Tests\Postgres\PlayerStats;

use App\PlayerStats\Adapters\Postgres\PostgresMatchEvents;
use Illuminate\Support\Facades\DB;
use Tests\Postgres\PostgresTestCase;

// The players list comes back in the database's name order (en_US.utf8 on dev), which a bytewise
// PHP sort doesn't reproduce: on the dev data the two differ for 141 of 142 players. The api image
// has no intl to collate in PHP, so the adapter leaves the order to Postgres (spec 004 D-6).
class NameOrderTest extends PostgresTestCase
{
    private const NAMES = ['zulu', 'Bravo', 'alpha', '[TAG] Zed', 'Émile', 'echo', '⑱ ✓ barbosauro', 'Charlie'];

    public function test_names_come_in_the_databases_collation(): void
    {
        $match = DB::table('matches')->insertGetId(['original_filename' => 'a.dem', 'demo_key' => 'a-key.dem', 'status' => 'parsed']);
        foreach (self::NAMES as $i => $name) {
            DB::table('match_player_stats')->insert(['match_id' => $match, 'steam_id' => (string) (76561190000000100 + $i), 'name' => $name]);
        }

        $names = array_map(fn ($p) => $p->name, (new PostgresMatchEvents(DB::connection()))->players([$match]));

        $collated = array_column(DB::select('select n from unnest(?::text[]) as n order by n', ['{"'.implode('","', self::NAMES).'"}']), 'n');
        $bytewise = self::NAMES;
        sort($bytewise, SORT_STRING);

        $this->assertSame($collated, $names);
        $this->assertNotSame($bytewise, $names);
    }
}
