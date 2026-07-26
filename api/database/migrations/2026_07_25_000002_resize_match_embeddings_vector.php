<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Swap-an-embedder scaffolding. A real embedder has its own vector width (voyage-3 =
// 1024, nomic-embed-text = 768), different from the hash stand-in's default. When you
// switch EMBED_PROVIDER, set EMBED_DIMENSIONS to the model's width and run this: it
// resizes the column to match, wiping the old vectors (they mean nothing to the new
// model). Then `php artisan analyst:embed` rebuilds the index — the projection is
// disposable by design.
//
// This is a no-op until EMBED_DIMENSIONS differs from the column's current width, so it
// is safe to keep in the migration set while you're still on the hash embedder.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // sqlite (tests) has no vector type
        }

        $target = (int) config('clutch.embed.dimensions', 256);
        $current = $this->currentWidth();

        if ($current === null || $current === $target) {
            return; // table not created yet, or already the right width
        }

        // pgvector can't ALTER a vector's dimension in place — drop the column's data and
        // re-add it at the new width. The vectors are a rebuildable projection, so this
        // loses nothing that `analyst:embed` can't regenerate from Postgres.
        DB::statement('ALTER TABLE match_embeddings DROP COLUMN embedding');
        DB::statement('ALTER TABLE match_embeddings ADD COLUMN embedding vector('.$target.')');
        DB::table('match_embeddings')->update(['embedded_at' => now()]); // mark all stale
    }

    public function down(): void
    {
        // Irreversible by nature — the old vectors are gone. Re-embed to restore.
    }

    private function currentWidth(): ?int
    {
        $row = DB::selectOne(
            "SELECT atttypmod FROM pg_attribute
             WHERE attrelid = 'match_embeddings'::regclass AND attname = 'embedding'"
        );

        // pgvector stores the declared dimension directly in atttypmod (no -4 offset).
        return $row?->atttypmod > 0 ? (int) $row->atttypmod : null;
    }
};
