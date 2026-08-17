<?php

namespace App\Search;

use App\Contracts\DocRetriever;
use App\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\DB;

// The third pgvector retriever, and the one that shows how little the other two actually
// shared: no match scoping, no visibility filter, a composite text key instead of an int.
// What IS shared is EmbeddingClient and the exact cosine scan — which is the real reusable
// seam, and it never needed an abstraction over the retrievers to stay reusable.
class PgVectorDocRetriever implements DocRetriever
{
    public function __construct(private EmbeddingClient $embeddings) {}

    public function related(string $query, int $limit = 3): array
    {
        $vector = $this->literal($this->embeddings->embed($query));

        $rows = DB::table('doc_embeddings')
            ->select('path', 'heading', 'document')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$vector])
            ->orderByRaw('embedding <=> ?', [$vector])
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'path' => $r->path,
            'heading' => $r->heading,
            'document' => $r->document,
            'similarity' => round((float) $r->similarity, 4),
        ])->all();
    }

    public function index(string $path, string $heading, string $document): void
    {
        $vector = $this->literal($this->embeddings->embed($document));

        DB::table('doc_embeddings')->where('path', $path)->where('heading', $heading)->delete();
        DB::statement(
            'INSERT INTO doc_embeddings (path, heading, document, embedding, embedded_at) VALUES (?, ?, ?, ?, now())',
            [$path, $heading, $document, $vector],
        );
    }

    public function forget(string $path): void
    {
        DB::table('doc_embeddings')->where('path', $path)->delete();
    }

    private function literal(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
