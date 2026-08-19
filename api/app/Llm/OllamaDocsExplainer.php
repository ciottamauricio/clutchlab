<?php

namespace App\Llm;

use App\Contracts\DocsLlm;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

// The docs explainer running locally in the ollama container. Same model and same
// tradeoffs as OllamaAnalyst, but a different prompt — which is the whole reason this is a
// class and not a flag: the grounding rules are a private const in each implementation, so
// "a different prompt" cannot be expressed as an argument.
class OllamaDocsExplainer implements DocsLlm
{
    // The measured failures behind the shared prompt in ExplainsFromDocs, kept here
    // because this is the model they were measured on.
    //
    // 1. Volume, not wording. At six excerpts (~8.5KB) the model abandoned the corpus and
    //    wrote seven generic advantages of Redis; at four (~4KB) it gave the project's
    //    actual reason — that Laravel's queue serializes PHP objects Go cannot read. Same
    //    prompt, same retrieval, same top score. Rewriting the instructions changed
    //    nothing; cutting the evidence changed everything. Hence CHUNK_LIMIT in
    //    AskDocsAction, which is where the number lives.
    //
    // 2. Citation FORM decides whether citation happens. [doc:path#heading] produced zero
    //    citations at every evidence size — a 7B model will not reproduce a long
    //    punctuated path mid-sentence. Numbered excerpts with [1]-style markers produced
    //    them immediately. AskDocsAction maps the numbers back to paths, so the wire
    //    format the frontend sees is unchanged by this concession to the model.
    use ExplainsFromDocs;

    public function __construct(
        private Http $http,
        private string $host,
        private string $model,
        private int $timeout,
    ) {}

    public function explain(string $question, array $chunks): string
    {
        $response = $this->http
            ->timeout($this->timeout)
            ->post(rtrim($this->host, '/').'/api/chat', [
                'model' => $this->model,
                'stream' => false,
                'options' => ['temperature' => 0.2],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM],
                    [
                        'role' => 'user',
                        'content' => self::render($chunks)."\n\nQUESTION: {$question}",
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Ollama chat failed ({$response->status()}): ".$response->body());
        }

        $text = trim((string) $response->json('message.content'));

        // The controller turns any throw into 503; an empty answer would instead render as
        // a blank, successful-looking response.
        if ($text === '') {
            throw new RuntimeException("Ollama model {$this->model} returned an empty answer.");
        }

        return $text;
    }
}
