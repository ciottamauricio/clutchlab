<?php

namespace App\Search;

use App\Contracts\EmbeddingClient;
use App\Contracts\SemanticRetriever;
use Illuminate\Support\Facades\DB;

// pgvector-backed semantic retriever. The query is embedded with the same client that
// built the index (they MUST match — mixing embedders makes distances meaningless),
// then nearest cards are found with cosine distance (`<=>`), scoped to the caller's
// visible matches server-side so results never escape what they may see.
class PgVectorRetriever implements SemanticRetriever
{
    public function __construct(private EmbeddingClient $embeddings) {}

    public function related(string $query, array $matchIds, int $limit = 5): array
    {
        if ($matchIds === []) {
            return [];
        }

        $vector = $this->literal($this->embeddings->embed($query));

        // `<=>` is cosine distance in [0,2]; similarity = 1 - distance. Ordering by the
        // raw operator lets the ivfflat index serve the query.
        $rows = DB::table('match_embeddings')
            ->whereIn('match_id', $matchIds)
            ->select('match_id', 'document')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$vector])
            ->orderByRaw('embedding <=> ?', [$vector])
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'match_id' => (int) $r->match_id,
            'document' => $r->document,
            'similarity' => round((float) $r->similarity, 4),
        ])->all();
    }

    public function index(int $matchId, string $document): void
    {
        $vector = $this->literal($this->embeddings->embed($document));

        // upsert can't set a vector column through the query builder's bindings cleanly,
        // so delete-then-insert with the vector cast in raw SQL.
        DB::table('match_embeddings')->where('match_id', $matchId)->delete();
        DB::statement(
            'INSERT INTO match_embeddings (match_id, document, embedding, embedded_at) VALUES (?, ?, ?, now())',
            [$matchId, $document, $vector],
        );
    }

    // pgvector's text input form: "[0.1,0.2,…]".
    private function literal(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
