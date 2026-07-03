<?php

namespace App\Actions;

use App\Contracts\DemoStorage;
use App\Contracts\SearchIndex;
use App\Models\GameMatch;

class DeleteMatchAction
{
    public function __construct(
        private DemoStorage $storage,
        private SearchIndex $search,
    ) {}

    public function execute(GameMatch $match): void
    {
        // Remove the read-model docs and the stored demo first; the DB row is the last
        // thing to go so a failure leaves the match visible and retryable rather than
        // orphaning storage/index entries behind a deleted record.
        $this->search->deleteByMatch('kills', $match->id);
        $this->search->deleteByMatch('rounds', $match->id);
        $this->storage->delete($match->demo_key);

        // kill_events / round_events / match_player_stats cascade via FK on delete.
        $match->delete();
    }
}
