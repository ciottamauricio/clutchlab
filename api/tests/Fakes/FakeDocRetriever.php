<?php

namespace Tests\Fakes;

use App\Contracts\DocRetriever;

// The docs-corpus fake. Same reason as its two siblings — sqlite has no vector column — but
// with no scope to record: this corpus is the same for every caller, which is exactly why
// it needed its own contract.
class FakeDocRetriever implements DocRetriever
{
    /** @var array<string, string> "path\0heading" => document */
    public array $indexed = [];

    /** @var list<array{path: string, heading: string, document: string, similarity: float}> */
    public array $hits = [];

    public ?string $lastQuery = null;

    /** @var list<string> */
    public array $forgotten = [];

    public function related(string $query, int $limit = 3): array
    {
        $this->lastQuery = $query;

        return array_slice($this->hits, 0, $limit);
    }

    public function index(string $path, string $heading, string $document): void
    {
        $this->indexed[$path."\0".$heading] = $document;
    }

    public function forget(string $path): void
    {
        $this->forgotten[] = $path;

        foreach (array_keys($this->indexed) as $key) {
            if (str_starts_with($key, $path."\0")) {
                unset($this->indexed[$key]);
            }
        }
    }
}
