<?php

namespace App\Actions\Analysis;

// Splits a markdown document into embeddable chunks. The plan called chunking "the whole
// game" and it is: too big and a chunk answers everything vaguely, too small and it
// answers nothing completely.
//
// These docs are already written as argument-per-heading, so the heading IS the chunk
// boundary — no sliding window, no token counting. Measured before choosing: no section in
// the corpus exceeds ~46 lines, so splitting at `##`/`###` needs no fallback splitter.
//
// The one thing a naive split gets wrong is CONTEXT. A chunk reading "Merge them when a
// real third corpus shows what they actually share" is unfindable and unusable alone: it
// never says what "them" is. So every chunk is prefixed with its file and heading
// ancestry, which is both the retrieval surface and what makes a retrieved chunk readable.
class ChunkMarkdownAction
{
    private const MIN_BODY_CHARS = 80;

    /**
     * @return list<array{heading: string, document: string}>
     */
    public function execute(string $path, string $markdown): array
    {
        $title = null;
        $h2 = null;
        $heading = null;
        $body = [];
        $chunks = [];
        $inFence = false;

        $flush = function () use (&$chunks, &$heading, &$body, $path, &$title) {
            if ($heading === null) {
                $body = [];

                return;
            }

            $text = trim(implode("\n", $body));
            // A heading with no prose under it is a table of contents entry, not an
            // argument. Embedding it adds a vector that can only ever match its own title.
            if (mb_strlen($text) >= self::MIN_BODY_CHARS) {
                $chunks[] = [
                    'heading' => $heading,
                    'document' => $this->contextualize($path, $title, $heading, $text),
                ];
            }

            $body = [];
        };

        foreach (explode("\n", $markdown) as $line) {
            // Headings inside fenced code are shell comments and bash prompts, not
            // structure — splitting on them tears a code block in half.
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = ! $inFence;
                $body[] = $line;

                continue;
            }

            if (! $inFence && preg_match('/^(#{1,3})\s+(.+?)\s*$/', $line, $m)) {
                $level = mb_strlen($m[1]);
                $text = $this->plain($m[2]);

                if ($level === 1) {
                    $flush();
                    $title = $text;
                    $h2 = null;
                    $heading = null;

                    continue;
                }

                $flush();

                if ($level === 2) {
                    $h2 = $text;
                    $heading = $text;
                } else {
                    // An h3 keeps its parent h2 in the key, so two files' "Why" sections
                    // and two sections' identically-named subheadings stay distinct.
                    $heading = $h2 === null ? $text : $h2.' › '.$text;
                }

                continue;
            }

            $body[] = $line;
        }

        $flush();

        return $chunks;
    }

    // What the embedder actually sees. The ancestry is repeated into the text on purpose:
    // "why is the parse queue a plain Redis list" should match a chunk that says "queue"
    // in its heading even when the body says only "it".
    private function contextualize(string $path, ?string $title, string $heading, string $body): string
    {
        $where = $title === null ? $path : $path.' — '.$title;

        return "From {$where}, section \"{$heading}\":\n\n{$body}";
    }

    // Strip the markdown that carries no meaning to an embedder: `code`, **bold**, links.
    private function plain(string $text): string
    {
        $text = preg_replace('/`([^`]*)`/', '$1', $text);
        $text = preg_replace('/\*\*([^*]*)\*\*/', '$1', $text);
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);

        return trim($text);
    }
}
