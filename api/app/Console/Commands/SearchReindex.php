<?php

namespace App\Console\Commands;

use App\Contracts\SearchIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Rebuilds the search read model from Postgres (the source of truth). This is what
// makes the index a projection you can always reconstruct — the CQRS safety net.
class SearchReindex extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Rebuild the search read model from Postgres';

    public function handle(SearchIndex $index): int
    {
        $index->configure();

        $kills = 0;
        DB::table('kill_events')->orderBy('id')->chunk(1000, function ($rows) use ($index, &$kills) {
            $docs = $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'match_id' => (int) $r->match_id,
                'owner_id' => (int) $r->owner_id,
                'map' => $r->map,
                'round' => (int) $r->round,
                'killer_name' => $r->killer_name,
                'victim_name' => $r->victim_name,
                'weapon' => $r->weapon,
                'headshot' => (bool) $r->headshot,
                'opening' => (bool) $r->opening,
                'side' => $r->side,
            ])->all();
            $index->indexDocuments('kills', $docs);
            $kills += count($docs);
        });

        $rounds = 0;
        DB::table('round_events')->orderBy('id')->chunk(1000, function ($rows) use ($index, &$rounds) {
            $docs = $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'match_id' => (int) $r->match_id,
                'owner_id' => (int) $r->owner_id,
                'map' => $r->map,
                'round' => (int) $r->round,
                'winner' => $r->winner,
                'reason' => $r->reason,
                'ct_alive' => (int) $r->ct_alive,
                't_alive' => (int) $r->t_alive,
                'ct_buy' => $r->ct_buy,
                't_buy' => $r->t_buy,
            ])->all();
            $index->indexDocuments('rounds', $docs);
            $rounds += count($docs);
        });

        $this->info("Reindexed {$kills} kills, {$rounds} rounds.");

        return self::SUCCESS;
    }
}
