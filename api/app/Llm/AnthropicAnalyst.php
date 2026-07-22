<?php

namespace App\Llm;

use Anthropic\Client;
use App\Contracts\AnalystLlm;

class AnthropicAnalyst implements AnalystLlm
{
    // The grounding rules live server-side, not in user input: answer from the evidence
    // only, cite matches so the frontend can link them, admit gaps instead of inventing.
    private const SYSTEM = <<<'PROMPT'
        You are the analyst for a Counter-Strike 2 team using Clutchlab.
        Answer the user's question using ONLY the JSON evidence provided — parsed
        match data from their own demos. Never invent matches, players, or numbers.

        The evidence has two kinds of data: recent_matches and kills_matching_question
        are OUTCOMES (what happened in games); recent_trainings are INTENT (what the
        team scheduled and practiced — tactics drilled, attendance, nade homework).
        When a question touches practice or preparation, connect the two: what was
        drilled, and how the matches around that date went.

        Rules:
        - Cite every match you draw a claim from as [match:ID] (the numeric id from
          the evidence). Place the citation right after the claim it supports.
        - Player and map names must be copied verbatim from the evidence.
        - If the evidence is insufficient to answer, say what is missing in one
          sentence — do not speculate.
        - Be concise: a short analytical answer (a few sentences, or a tight list),
          not a report. Plain text only, no markdown headings.
        PROMPT;

    public function __construct(private Client $client, private string $model) {}

    public function answer(string $question, array $evidence): string
    {
        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: 1024,
            system: self::SYSTEM,
            messages: [[
                'role' => 'user',
                'content' => "EVIDENCE:\n".json_encode($evidence, JSON_UNESCAPED_UNICODE)."\n\nQUESTION: {$question}",
            ]],
        );

        // Content is polymorphic (thinking blocks may precede text) — take the text blocks.
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }
}
