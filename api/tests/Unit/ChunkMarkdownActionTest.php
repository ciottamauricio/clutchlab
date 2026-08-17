<?php

namespace Tests\Unit;

use App\Actions\Analysis\ChunkMarkdownAction;
use Tests\TestCase;

// Chunking is the whole game for a docs corpus: the retrieval unit is decided here, and a
// bad boundary can't be recovered downstream by a better prompt or a better model.
class ChunkMarkdownActionTest extends TestCase
{
    private function chunk(string $markdown, string $path = 'docs/ARCHITECTURE.md'): array
    {
        return (new ChunkMarkdownAction)->execute($path, $markdown);
    }

    public function test_it_splits_on_headings_and_keeps_the_body_with_its_heading(): void
    {
        $chunks = $this->chunk(<<<'MD'
        # Architecture

        ## The parse queue

        A plain Redis list, because the worker is Go and Laravel's queue payload format
        is a PHP implementation detail that Go would have to reimplement.

        ## The event channel

        Redis pub/sub carrying JSON, additive changes only, pinned by contract fixtures
        so a publisher and its subscribers can never drift apart silently.
        MD);

        $this->assertCount(2, $chunks);
        $this->assertSame('The parse queue', $chunks[0]['heading']);
        $this->assertStringContainsString('plain Redis list', $chunks[0]['document']);
        $this->assertStringNotContainsString('pub/sub', $chunks[0]['document']);
    }

    // The failure this prevents: a chunk saying "it is additive only" that never says what
    // "it" is. Retrieval finds text, so the context has to BE text.
    public function test_every_chunk_carries_its_file_and_heading_as_text(): void
    {
        $chunks = $this->chunk(<<<'MD'
        # Architecture

        ## Why Go for the worker

        Parsing is CPU-bound and the demo library is Go, so it stays where the library is
        rather than being reimplemented in PHP for uniformity's sake.
        MD);

        $document = $chunks[0]['document'];
        $this->assertStringContainsString('docs/ARCHITECTURE.md', $document);
        $this->assertStringContainsString('Architecture', $document);
        $this->assertStringContainsString('Why Go for the worker', $document);
    }

    public function test_a_subsection_keeps_its_parent_heading_so_generic_names_stay_distinct(): void
    {
        $chunks = $this->chunk(<<<'MD'
        # Analyst

        ## Retrieval

        ### Why

        Two retrievers run on every question because they fail in different ways, and the
        overlap between them is what keeps a bad day from becoming a wrong answer.
        MD);

        $this->assertSame('Retrieval › Why', $chunks[0]['heading']);
    }

    // A `## Setup` followed by a bash block full of `# comments` must stay one chunk.
    public function test_headings_inside_code_fences_are_not_boundaries(): void
    {
        $chunks = $this->chunk(<<<'MD'
        # Engineering

        ## Running it

        Bring the stack up from the repo root, which is the only supported entry point:

        ```bash
        # start everything
        docker compose up -d
        # then migrate
        docker compose exec api php artisan migrate
        ```
        MD);

        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('start everything', $chunks[0]['document']);
    }

    // A heading with nothing under it is a table of contents entry. Embedded, it produces a
    // vector that can only ever match its own title — noise that displaces a real answer.
    public function test_headings_with_no_prose_are_dropped(): void
    {
        $chunks = $this->chunk(<<<'MD'
        # Index

        ## Contents

        ## The real section

        This one has enough actual prose under it to be worth retrieving on its own, which
        is the entire distinction being drawn here.
        MD);

        $this->assertCount(1, $chunks);
        $this->assertSame('The real section', $chunks[0]['heading']);
    }

    public function test_markdown_syntax_is_stripped_from_headings(): void
    {
        $chunks = $this->chunk(<<<'MD'
        # Doc

        ## The `parse_queue` and **why** it matters

        Backticks and bold markers carry no meaning to an embedder trained on prose, so
        they are removed before the heading becomes part of the retrieval surface.
        MD);

        $this->assertSame('The parse_queue and why it matters', $chunks[0]['heading']);
    }

    public function test_a_document_with_no_headings_yields_nothing(): void
    {
        $this->assertSame([], $this->chunk("Just a paragraph with no structure at all, which gives the chunker no boundary to work with."));
    }
}
