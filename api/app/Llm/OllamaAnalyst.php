<?php

namespace App\Llm;

use App\Contracts\AnalystLlm;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

// The analyst's generator, run locally in the ollama container: no account, no per-call
// bill, and no evidence leaving the machine. The tradeoff is real — a 7B model holds the
// grounding rules below less reliably than Claude does, so ANALYST_PROVIDER exists to
// switch back. AskAnalystAction never learns which one answered.
class OllamaAnalyst implements AnalystLlm
{
    // Identical rules to AnthropicAnalyst: the frontend turns [match:ID] into chips that
    // open the match, so an invented id becomes a broken link. Small models drift from
    // instructions more easily, which makes the "admit gaps" clause matter more here, not
    // less — the prompt has to keep giving the model a way to say it doesn't know.
    private const SYSTEM = <<<'PROMPT'
        You are the analyst for a Counter-Strike 2 team using Clutchlab.
        Answer the user's question using ONLY the JSON evidence provided — parsed
        match data from their own demos. Never invent matches, players, or numbers.

        The evidence has three kinds of data. recent_matches and kills_matching_question
        are OUTCOMES (what happened in games). recent_trainings are INTENT (what the team
        scheduled and practiced — tactics drilled, attendance, nade homework); when a
        question touches practice or preparation, connect the two: what was drilled, and
        how the matches around that date went. semantically_related_matches are matches
        whose summary is closest in meaning to the question — use them to find relevant
        games that may fall outside the recent window, but weight them by their
        similarity score and don't force a connection when it's weak.

        Rules:
        - Cite every match you draw a claim from as [match:ID] (the numeric id from
          the evidence). Place the citation right after the claim it supports.
        - Player and map names must be copied verbatim from the evidence.
        - If the evidence is insufficient to answer, say what is missing in one
          sentence — do not speculate.
        - Be concise: a short analytical answer (a few sentences, or a tight list),
          not a report. Plain text only, no markdown headings.
        PROMPT;

    public function __construct(
        private Http $http,
        private string $host,
        private string $model,
        private int $timeout,
    ) {}

    public function answer(string $question, array $evidence): string
    {
        $response = $this->http
            ->timeout($this->timeout)
            ->post(rtrim($this->host, '/').'/api/chat', [
                'model' => $this->model,
                'stream' => false,
                // Near-deterministic: this is evidence summarization, not writing. Sampling
                // freely is exactly how a small model starts inventing match ids.
                'options' => ['temperature' => 0.2],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM],
                    [
                        'role' => 'user',
                        'content' => "EVIDENCE:\n".json_encode($evidence, JSON_UNESCAPED_UNICODE)."\n\nQUESTION: {$question}",
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Ollama chat failed ({$response->status()}): ".$response->body());
        }

        $text = trim((string) $response->json('message.content'));

        // The controller turns any throw into 503 analyst.unavailable; an empty answer
        // would instead render as a blank, successful-looking response.
        if ($text === '') {
            throw new RuntimeException("Ollama model {$this->model} returned an empty answer.");
        }

        return $text;
    }
}
