<?php

namespace App\Search;

use App\Contracts\SearchIndex;
use Meilisearch\Client;

class MeilisearchIndex implements SearchIndex
{
    // Filterable/searchable/sortable attributes per index (used by configure()).
    private const SETTINGS = [
        'kills' => [
            'filterable' => ['owner_id', 'match_id', 'map', 'weapon', 'headshot', 'opening', 'side', 'killer_team', 'clutch', 'round', 'killer_name', 'victim_name', 'killer_steam_id'],
            'searchable' => ['killer_name', 'victim_name', 'weapon', 'map'],
            'sortable' => ['round'],
        ],
        'rounds' => [
            'filterable' => ['owner_id', 'match_id', 'map', 'winner', 'reason', 'ct_alive', 't_alive', 'ct_buy', 't_buy', 'round'],
            'searchable' => ['map', 'reason'],
            'sortable' => ['round'],
        ],
    ];

    public function __construct(private Client $client) {}

    public function search(string $index, string $query, array $filters, int $ownerId, int $limit = 50): array
    {
        // owner_id is forced server-side — never trust a client-supplied owner.
        $clauses = ['owner_id = '.$ownerId];
        foreach ($filters as $field => $value) {
            // An array value becomes an IN clause (e.g. a team's roster steam_ids); a scalar
            // is plain equality.
            if (is_array($value)) {
                $list = implode(', ', array_map(fn ($v) => $this->literal($v), $value));
                $clauses[] = $field.' IN ['.$list.']';
            } else {
                $clauses[] = $field.' = '.$this->literal($value);
            }
        }

        $result = $this->client->index($index)->search($query, [
            'filter' => implode(' AND ', $clauses),
            'limit' => $limit,
        ]);

        return [
            'hits' => $result->getHits(),
            'total' => $result->getEstimatedTotalHits(),
        ];
    }

    public function indexDocuments(string $index, array $documents): void
    {
        if ($documents === []) {
            return;
        }
        $this->client->index($index)->addDocuments($documents, 'id');
    }

    public function deleteByMatch(string $index, int $matchId): void
    {
        $this->client->index($index)->deleteDocuments(['filter' => 'match_id = '.$matchId]);
    }

    public function facets(string $index, string $field, int $ownerId): array
    {
        $result = $this->client->index($index)->search('', [
            'filter' => 'owner_id = '.$ownerId,
            'facets' => [$field],
            'limit' => 0,
        ]);

        return $result->getFacetDistribution()[$field] ?? [];
    }

    public function configure(): void
    {
        foreach (self::SETTINGS as $name => $attrs) {
            try {
                $this->client->createIndex($name, ['primaryKey' => 'id']);
            } catch (\Throwable) {
                // already exists — fine
            }
            $index = $this->client->index($name);
            $index->updateFilterableAttributes($attrs['filterable']);
            $index->updateSearchableAttributes($attrs['searchable']);
            $index->updateSortableAttributes($attrs['sortable']);
        }
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default => '"'.addslashes((string) $value).'"',
        };
    }
}
