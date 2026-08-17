<?php

namespace Tests\Fakes;

use App\Contracts\RoundRetriever;

// FakeSemanticRetriever's round-level twin: the sqlite test DB has no vector column, so
// the store is faked and tests assert on what was indexed and what scope was asked for.
class FakeRoundRetriever implements RoundRetriever
{
    /** @var array<string, string> "matchId:round" => document */
    public array $indexed = [];

    /** @var list<array{match_id: int, round: int, document: string, similarity: float}> */
    public array $hits = [];

    public ?string $lastQuery = null;

    /** @var list<int>|null */
    public ?array $lastScope = null;

    /** @var list<int> */
    public array $forgotten = [];

    public function related(string $query, array $matchIds, int $limit = 3): array
    {
        $this->lastQuery = $query;
        $this->lastScope = $matchIds;

        return array_slice(
            array_values(array_filter($this->hits, fn ($h) => in_array($h['match_id'], $matchIds, true))),
            0,
            $limit,
        );
    }

    public function index(int $matchId, int $round, string $document): void
    {
        $this->indexed["{$matchId}:{$round}"] = $document;
    }

    public function forget(int $matchId): void
    {
        $this->forgotten[] = $matchId;

        foreach (array_keys($this->indexed) as $key) {
            if (str_starts_with($key, "{$matchId}:")) {
                unset($this->indexed[$key]);
            }
        }
    }
}
