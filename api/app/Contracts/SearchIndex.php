<?php

namespace App\Contracts;

// The read side of the search read model. Callers depend on this, not on a concrete
// engine, so Meilisearch can be swapped for Elasticsearch by binding a different
// implementation (see api/docs/domains/search.md).
interface SearchIndex
{
    /**
     * Search one index, always scoped to the owner. Returns ['hits' => array, 'total' => int].
     *
     * @param  array<string, mixed>  $filters  field => value (ANDed with owner_id)
     * @return array{hits: array<int, mixed>, total: int}
     */
    public function search(string $index, string $query, array $filters, int $ownerId, int $limit = 50): array;

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    public function indexDocuments(string $index, array $documents): void;

    public function deleteByMatch(string $index, int $matchId): void;

    /** Create the indexes and set their filterable/searchable/sortable attributes. */
    public function configure(): void;
}
