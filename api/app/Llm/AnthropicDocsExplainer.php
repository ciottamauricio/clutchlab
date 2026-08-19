<?php

namespace App\Llm;

use Anthropic\Client;
use App\Contracts\DocsLlm;

// The hosted docs explainer. Prompt and evidence rendering come from ExplainsFromDocs, so
// the only thing that differs between the two providers is the model behind them.
class AnthropicDocsExplainer implements DocsLlm
{
    use ExplainsFromDocs;

    public function __construct(private Client $client, private string $model) {}

    public function explain(string $question, array $chunks): string
    {
        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: 1024,
            system: self::SYSTEM,
            messages: [[
                'role' => 'user',
                'content' => self::render($chunks)."\n\nQUESTION: {$question}",
            ]],
        );

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }
}
