<?php

namespace App\Llm;

use App\Contracts\EmbeddingClient;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

// A learned embedder served by the local Ollama container — the real fix for the
// limitation HashEmbeddings documents: this one places "duel" near "fight" because the
// model was trained on meaning, not on token identity.
//
// Runs on the Docker network with no account and no per-call bill, so unlike the
// analyst's generator there's nothing to degrade gracefully around: embedding happens in
// `analyst:embed` (a batch command), never inside a web request.
class OllamaEmbeddings implements EmbeddingClient
{
    public function __construct(
        private Http $http,
        private string $host,
        private string $model,
        private int $dimensions,
    ) {}

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embed(string $text): array
    {
        $response = $this->http
            ->timeout(30)
            ->post(rtrim($this->host, '/').'/api/embeddings', [
                'model' => $this->model,
                'prompt' => $text,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Ollama embedding failed ({$response->status()}): ".$response->body());
        }

        $vector = $response->json('embedding');

        // A width mismatch would be stored as-is and only surface later as a pgvector
        // insert error with no hint of the cause, so name it here: EMBED_DIMENSIONS, the
        // column width, and the model must all agree.
        if (! is_array($vector) || count($vector) !== $this->dimensions) {
            $got = is_array($vector) ? count($vector) : 'none';

            throw new RuntimeException(
                "Ollama model {$this->model} returned {$got} dimensions, expected {$this->dimensions}. ".
                'Set EMBED_DIMENSIONS to the model width and re-run migrate + analyst:embed.'
            );
        }

        return array_map(fn ($v) => (float) $v, $vector);
    }
}
