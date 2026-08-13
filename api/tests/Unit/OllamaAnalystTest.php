<?php

namespace Tests\Unit;

use App\Llm\OllamaAnalyst;
use Illuminate\Http\Client\Factory as Http;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// The HTTP client is faked, so no ollama container is needed and no request ever leaves
// the test. Assertions target what we SEND (the grounding prompt, the evidence, a low
// temperature) and how failures surface — never generated prose, which no test can pin.
class OllamaAnalystTest extends TestCase
{
    private function make(Http $http): OllamaAnalyst
    {
        return new OllamaAnalyst($http, 'http://ollama:11434', 'qwen2.5-coder:7b', 120);
    }

    private function httpReturning(array $body, int $status = 200): Http
    {
        $http = new Http;
        $http->fake(['*' => $http->response($body, $status)]);

        return $http;
    }

    public function test_returns_the_models_answer(): void
    {
        $http = $this->httpReturning(['message' => ['content' => "  You lose Mirage retakes. [match:7]  \n"]]);

        $this->assertSame(
            'You lose Mirage retakes. [match:7]',
            $this->make($http)->answer('how do we lose on mirage?', ['recent_matches' => []]),
        );
    }

    public function test_sends_the_grounding_prompt_and_the_evidence(): void
    {
        $http = $this->httpReturning(['message' => ['content' => 'ok']]);

        $this->make($http)->answer('who gets our opening kills?', ['recent_matches' => [['id' => 42]]]);

        $http->assertSent(function ($request) {
            [$system, $user] = $request['messages'];

            return $request->url() === 'http://ollama:11434/api/chat'
                && $request['model'] === 'qwen2.5-coder:7b'
                && $request['stream'] === false
                && $system['role'] === 'system'
                && str_contains($system['content'], '[match:ID]')
                && str_contains($user['content'], 'who gets our opening kills?')
                && str_contains($user['content'], '"id":42');
        });
    }

    public function test_generation_is_near_deterministic_so_ids_are_not_invented(): void
    {
        $http = $this->httpReturning(['message' => ['content' => 'ok']]);

        $this->make($http)->answer('q', []);

        $http->assertSent(fn ($request) => $request['options']['temperature'] === 0.2);
    }

    // The controller maps a throw to 503 analyst.unavailable; both of these must throw
    // rather than return, or a failure renders as a blank but successful answer.
    public function test_an_unreachable_or_erroring_ollama_surfaces_the_status(): void
    {
        $http = $this->httpReturning(['error' => 'model not found'], 404);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/404/');

        $this->make($http)->answer('q', []);
    }

    public function test_an_empty_answer_is_a_failure_not_a_blank_response(): void
    {
        $http = $this->httpReturning(['message' => ['content' => '   ']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty answer/');

        $this->make($http)->answer('q', []);
    }
}
