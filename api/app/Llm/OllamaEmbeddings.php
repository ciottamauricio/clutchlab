<?php

namespace App\Llm;

use App\Contracts\EmbeddingClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
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
    private const RETRIES = 2;

    public function __construct(
        private Http $http,
        private string $host,
        private string $model,
        private int $dimensions,
        private int $timeout = 120,
    ) {}

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embed(string $text): array
    {
        $response = $this->request($text);

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

    // Retried because the failure this guards against is real and observed: on a desktop
    // GPU the model is loaded and evicted between calls depending on what ELSE holds VRAM
    // (on WSL2 the Windows desktop's usage counts against the same 8GB and is invisible
    // from Linux). A batch of a few hundred chunks will hit a reload, and a reload under
    // memory pressure is slow enough to look like a hang.
    //
    // Backing off between attempts matters more than the count: retrying instantly just
    // spends the budget while the runner is still busy failing.
    private function request(string $text): Response
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return $this->http
                    ->timeout($this->timeout)
                    ->post(rtrim($this->host, '/').'/api/embeddings', [
                        'model' => $this->model,
                        'prompt' => $text,
                    ]);
            } catch (ConnectionException $e) {
                if ($attempt >= self::RETRIES) {
                    throw new RuntimeException(
                        "Ollama embedding timed out after {$this->timeout}s and ".self::RETRIES.' retries. '.
                        'A local model that stalls is usually a VRAM question: check `ollama ps` for '.
                        'CPU fallback and free GPU memory before assuming the model is at fault.',
                        0,
                        $e,
                    );
                }

                sleep(2 * ($attempt + 1));
            }
        }
    }
}
