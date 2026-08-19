<?php

namespace Tests\Fakes;

use App\Contracts\DocsLlm;

// Records the chunks the docs loop hands to the model. Tests assert on WHAT was retrieved
// and how citations are resolved, never on generated prose — the same discipline as
// SpyAnalystLlm, and for the same reason: a real model's wording is not a fixture.
class SpyDocsLlm implements DocsLlm
{
    public ?string $question = null;

    /** @var list<array{path: string, heading: string, document: string, similarity: float}> */
    public array $chunks = [];

    // Numbered markers, because that is what the real prompt asks the model for and what
    // AskDocsAction expands back into paths.
    public string $reply = 'Because the worker is written in Go [1].';

    public ?\Throwable $failWith = null;

    public function explain(string $question, array $chunks): string
    {
        if ($this->failWith) {
            throw $this->failWith;
        }

        $this->question = $question;
        $this->chunks = $chunks;

        return $this->reply;
    }
}
