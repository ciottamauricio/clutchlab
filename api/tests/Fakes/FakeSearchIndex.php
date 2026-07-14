<?php

namespace Tests\Fakes;

use App\Contracts\SearchIndex;

// No-op search index — tests never talk to Meilisearch.
class FakeSearchIndex implements SearchIndex
{
    public function search(string $index, string $query, array $filters, array $matchIds, int $limit = 50): array
    {
        return ['hits' => [], 'total' => 0];
    }

    public function indexDocuments(string $index, array $documents): void {}

    public function deleteByMatch(string $index, int $matchId): void {}

    public function facets(string $index, string $field, array $matchIds): array
    {
        return [];
    }

    public function configure(): void {}
}
