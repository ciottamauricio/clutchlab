<?php

namespace App\Contracts;

// Turns text into a fixed-width vector for semantic search. A seam like every other
// external system: the default is a keyless local hash embedder (study-grade), but a
// real provider (Voyage, OpenAI, an Ollama model) can be bound in its place without
// touching the retriever or the action.
//
// Each embedder reports its own vector width via dimensions() — a real model's width is
// ITS property (voyage-3 = 1024, nomic-embed-text = 768), not a global constant. The
// pgvector column must be that same width, so a new embedder means: set EMBED_DIMENSIONS,
// migrate the column, and re-embed (the index is a rebuildable projection).
interface EmbeddingClient
{
    /** The fixed number of components every vector this embedder returns has. */
    public function dimensions(): int;

    /** @return list<float> a vector of exactly dimensions() components */
    public function embed(string $text): array;
}
