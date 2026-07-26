<?php

namespace Tests\Unit;

use App\Llm\HashEmbeddings;
use PHPUnit\Framework\TestCase;

// The embedder is pure math — no DB, no key — so it unit-tests directly. These pin the
// invariants pgvector and cosine search rely on: it reports and returns the configured
// width, vectors are unit length, and word overlap moves vectors closer than unrelated text.
class HashEmbeddingsTest extends TestCase
{
    private const DIMS = 256;

    private function make(): HashEmbeddings
    {
        return new HashEmbeddings(self::DIMS);
    }

    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        foreach ($a as $i => $x) {
            $dot += $x * $b[$i];
        }

        return $dot; // both are unit vectors, so dot product IS cosine similarity
    }

    public function test_vectors_have_the_configured_width_the_column_expects(): void
    {
        $e = $this->make();
        $this->assertSame(self::DIMS, $e->dimensions());
        $this->assertCount(self::DIMS, $e->embed('de_mirage awp opening kills'));
    }

    public function test_width_follows_the_constructor_so_the_column_can_be_matched(): void
    {
        $this->assertCount(64, (new HashEmbeddings(64))->embed('narrower vector'));
    }

    public function test_vectors_are_unit_length(): void
    {
        $vec = $this->make()->embed('some text with several tokens');
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vec)));
        $this->assertEqualsWithDelta(1.0, $norm, 1e-9);
    }

    public function test_empty_text_is_a_zero_vector_not_a_crash(): void
    {
        $vec = $this->make()->embed('   ');
        $this->assertSame(0.0, array_sum(array_map('abs', $vec)));
    }

    public function test_shared_words_are_closer_than_unrelated_text(): void
    {
        $e = $this->make();
        $base = $e->embed('inferno banana site smoke execute');
        $near = $e->embed('inferno banana smoke');
        $far = $e->embed('dust2 long doors awp');

        $this->assertGreaterThan($this->cosine($base, $far), $this->cosine($base, $near));
    }

    public function test_light_stemming_collapses_plurals(): void
    {
        $e = $this->make();
        // "kill"/"kills" and "round"/"rounds" should map together, so these are identical.
        $this->assertEquals($e->embed('kill round'), $e->embed('kills rounds'));
    }
}
