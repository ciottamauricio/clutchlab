<?php

namespace App\Contracts;

// The semantic half of retrieval: nearest match "cards" by embedding distance, the
// complement to the keyword SearchIndex. Behind an interface so the vector store is
// swappable (pgvector today; a dedicated vector DB, or LLPhant's store, later) and so
// tests fake it — no vector column exists on the sqlite test DB.
interface SemanticRetriever
{
    /**
     * The matches whose embedded card is closest to the query, restricted to the
     * caller's visible set. Returns [['match_id' => int, 'document' => string,
     * 'similarity' => float], …] ordered nearest-first.
     *
     * @param  list<int>  $matchIds  the matches the caller may see; results never escape this set
     * @return list<array{match_id: int, document: string, similarity: float}>
     */
    public function related(string $query, array $matchIds, int $limit = 5): array;

    /** Store (or replace) one match's card and its vector. */
    public function index(int $matchId, string $document): void;
}
