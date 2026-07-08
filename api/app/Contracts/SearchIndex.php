<?php

namespace App\Contracts;

// The read side of the search read model. Callers depend on this, not on a concrete
// engine, so Meilisearch can be swapped for Elasticsearch by binding a different
// implementation (see api/docs/domains/search.md).
interface SearchIndex
{
    /**
     * Search one index, always scoped to a set of visible match ids. Returns
     * ['hits' => array, 'total' => int].
     *
     * @param  array<string, mixed>  $filters  field => value (ANDed with the match scope)
     * @param  list<int>  $matchIds  the matches the caller may see; results never escape this set
     * @return array{hits: array<int, mixed>, total: int}
     */
    public function search(string $index, string $query, array $filters, array $matchIds, int $limit = 50): array;

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    public function indexDocuments(string $index, array $documents): void;

    public function deleteByMatch(string $index, int $matchId): void;

    /**
     * Facet distribution for one filterable field, scoped to the visible matches: [value => count].
     *
     * @param  list<int>  $matchIds  the matches the caller may see
     * @return array<string, int>
     */
    public function facets(string $index, string $field, array $matchIds): array;

    /** Create the indexes and set their filterable/searchable/sortable attributes. */
    public function configure(): void;
}
