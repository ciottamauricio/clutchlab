<?php

namespace Tests\Fakes;

use App\Contracts\SemanticRetriever;

// The sqlite test DB has no vector column, so the semantic retriever is faked. Records
// what was indexed and returns whatever hits a test primes — enough to assert the
// action merges semantic evidence and honors the visible scope, without a real store.
class FakeSemanticRetriever implements SemanticRetriever
{
    /** @var array<int, string> match_id => document */
    public array $indexed = [];

    /** @var list<array{match_id: int, document: string, similarity: float}> */
    public array $hits = [];

    public ?string $lastQuery = null;

    /** @var list<int>|null */
    public ?array $lastScope = null;

    public function related(string $query, array $matchIds, int $limit = 5): array
    {
        $this->lastQuery = $query;
        $this->lastScope = $matchIds;

        // Honor the visible scope like the real one does, then cap at the limit.
        return array_slice(
            array_values(array_filter($this->hits, fn ($h) => in_array($h['match_id'], $matchIds, true))),
            0,
            $limit,
        );
    }

    public function index(int $matchId, string $document): void
    {
        $this->indexed[$matchId] = $document;
    }
}
