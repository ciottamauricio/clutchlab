<?php

namespace Tests\Fakes;

use App\Contracts\AnalystLlm;

// Records what the RAG loop hands to the model — tests assert on the retrieved
// evidence, never on generated prose. Never calls the Anthropic API.
class SpyAnalystLlm implements AnalystLlm
{
    public ?string $question = null;

    /** @var array<string, mixed> */
    public array $evidence = [];

    public string $reply = 'Fake analysis. [match:1]';

    public ?\Throwable $failWith = null;

    public function answer(string $question, array $evidence): string
    {
        if ($this->failWith) {
            throw $this->failWith;
        }

        $this->question = $question;
        $this->evidence = $evidence;

        return $this->reply;
    }
}
