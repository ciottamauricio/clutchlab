<?php

namespace App\Search;

use App\Contracts\EmbeddingClient;
use App\Contracts\RoundRetriever;
use Illuminate\Support\Facades\DB;

// PgVectorRetriever's round-level twin: same store, same embedder, same exact cosine
// scan — only the grain and the key differ. Scoping is still by match id, because that's
// the unit visibility is defined on.
class PgVectorRoundRetriever implements RoundRetriever
{
    public function __construct(private EmbeddingClient $embeddings) {}

    public function related(string $query, array $matchIds, int $limit = 3): array
    {
        if ($matchIds === []) {
            return [];
        }

        $vector = $this->literal($this->embeddings->embed($query));

        $rows = DB::table('round_embeddings')
            ->whereIn('match_id', $matchIds)
            ->select('match_id', 'round', 'document')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$vector])
            ->orderByRaw('embedding <=> ?', [$vector])
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'match_id' => (int) $r->match_id,
            'round' => (int) $r->round,
            'document' => $r->document,
            'similarity' => round((float) $r->similarity, 4),
        ])->all();
    }

    public function index(int $matchId, int $round, string $document): void
    {
        $vector = $this->literal($this->embeddings->embed($document));

        DB::table('round_embeddings')->where('match_id', $matchId)->where('round', $round)->delete();
        DB::statement(
            'INSERT INTO round_embeddings (match_id, round, document, embedding, embedded_at) VALUES (?, ?, ?, ?, now())',
            [$matchId, $round, $document, $vector],
        );
    }

    public function forget(int $matchId): void
    {
        DB::table('round_embeddings')->where('match_id', $matchId)->delete();
    }

    private function literal(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
