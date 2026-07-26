<?php

namespace App\Llm;

use App\Contracts\EmbeddingClient;

// A dependency-free, keyless embedder: the "hashing trick". Each token is hashed into
// one of N buckets and its weight accumulated, then the vector is L2-normalized so
// cosine distance is meaningful. This is NOT semantically smart — it captures word
// overlap with light stemming, not meaning ("duel" and "fight" land in different
// buckets). It exists so the whole pgvector architecture runs for real with no external
// account; swap in a learned embedder behind EmbeddingClient for real semantics.
class HashEmbeddings implements EmbeddingClient
{
    // The hash embedder has no natural width, so it takes whatever the column is set to
    // (config('clutch.embed.dimensions')). A real embedder would hard-code its model's width.
    public function __construct(private int $dimensions) {}

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embed(string $text): array
    {
        $vec = array_fill(0, $this->dimensions, 0.0);

        foreach ($this->tokens($text) as $token) {
            // Two hashes: one picks the bucket, one picks the sign — signed hashing
            // reduces collisions cancelling into a systematic bias.
            $bucket = crc32($token) % $this->dimensions;
            $sign = (crc32('s'.$token) % 2) === 0 ? 1.0 : -1.0;
            $vec[$bucket] += $sign;
        }

        return $this->normalize($vec);
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        $text = strtolower($text);
        preg_match_all('/[a-z0-9_]+/', $text, $m);

        // Crude stemming so "kills"/"kill" and "rounds"/"round" collide on purpose.
        return array_map(fn (string $t) => rtrim($t, 's'), $m[0]);
    }

    /** @param list<float> $vec @return list<float> */
    private function normalize(array $vec): array
    {
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vec)));
        if ($norm === 0.0) {
            return $vec;
        }

        return array_map(fn ($x) => $x / $norm, $vec);
    }
}
