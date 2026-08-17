<?php

namespace App\Contracts;

// Semantic retrieval at round granularity — SemanticRetriever's twin, one level down.
// Deliberately a SECOND interface rather than a `corpus` parameter on the first: today
// there is one implementation of each (pgvector), and a shared abstraction invented for
// two similar cases tends to fit neither once a third arrives (embedded docs, for
// instance, have no match to scope by at all). Merge them when a real third corpus proves
// what they actually share.
interface RoundRetriever
{
    /**
     * Rounds whose embedded card is closest to the query, restricted to the caller's
     * visible matches — scoping is by MATCH, since that's the unit visibility is defined
     * on; a round is visible exactly when its match is.
     *
     * @param  list<int>  $matchIds  the matches the caller may see; results never escape this set
     * @return list<array{match_id: int, round: int, document: string, similarity: float}>
     */
    public function related(string $query, array $matchIds, int $limit = 3): array;

    /** Store (or replace) one round's card and its vector. */
    public function index(int $matchId, int $round, string $document): void;

    /** Drop every stored round for a match, so a re-parse can't leave orphans behind. */
    public function forget(int $matchId): void;
}
