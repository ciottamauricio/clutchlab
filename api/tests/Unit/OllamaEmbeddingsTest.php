<?php

namespace Tests\Unit;

use App\Llm\OllamaEmbeddings;
use Illuminate\Http\Client\Factory as Http;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// The HTTP client is faked, so no ollama container is needed and no request ever leaves
// the test. What's pinned here is the contract's edge: a vector of exactly dimensions()
// components, and a loud failure when the model's width disagrees with EMBED_DIMENSIONS
// (silently storing the wrong width is what turns into an opaque pgvector insert error).
class OllamaEmbeddingsTest extends TestCase
{
    private const DIMS = 768;

    private function make(Http $http, int $dimensions = self::DIMS): OllamaEmbeddings
    {
        return new OllamaEmbeddings($http, 'http://ollama:11434', 'nomic-embed-text', $dimensions);
    }

    private function httpReturning(array $body, int $status = 200): Http
    {
        $http = new Http;
        $http->fake(['*' => $http->response($body, $status)]);

        return $http;
    }

    public function test_reports_the_width_the_column_is_migrated_to(): void
    {
        $this->assertSame(self::DIMS, $this->make(new Http)->dimensions());
    }

    public function test_returns_the_models_vector_as_floats(): void
    {
        $http = $this->httpReturning(['embedding' => array_fill(0, self::DIMS, 0.5)]);

        $vec = $this->make($http)->embed('awp opening kills on mirage');

        $this->assertCount(self::DIMS, $vec);
        $this->assertSame(0.5, $vec[0]);
    }

    public function test_posts_the_text_to_the_configured_model(): void
    {
        $http = $this->httpReturning(['embedding' => array_fill(0, self::DIMS, 0.1)]);

        $this->make($http)->embed('banana site execute');

        $http->assertSent(function ($request) {
            return $request->url() === 'http://ollama:11434/api/embeddings'
                && $request['model'] === 'nomic-embed-text'
                && $request['prompt'] === 'banana site execute';
        });
    }

    public function test_a_width_mismatch_fails_loudly_instead_of_storing_a_bad_vector(): void
    {
        // The model answers with its real width (768) while EMBED_DIMENSIONS says 256.
        $http = $this->httpReturning(['embedding' => array_fill(0, 768, 0.1)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/expected 256/');

        $this->make($http, 256)->embed('mismatched width');
    }

    public function test_an_unreachable_or_erroring_ollama_surfaces_the_status(): void
    {
        $http = $this->httpReturning(['error' => 'model not found'], 404);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/404/');

        $this->make($http)->embed('model was never pulled');
    }
}
