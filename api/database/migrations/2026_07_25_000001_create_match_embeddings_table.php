<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The semantic read model: one embedded "card" per parsed match, searched by vector
// distance. Postgres-only (pgvector); the sqlite test DB has no vector type, so the
// column is added conditionally and the retriever is faked in tests.
return new class extends Migration
{
    public function up(): void
    {
        // The column width must equal the active embedder's dimensions() — config is the
        // single source both read (see EMBED_DIMENSIONS). A later migration widens this
        // when you switch to a higher-dimensional model.
        $dimensions = (int) config('clutch.embed.dimensions', 256);

        $pg = DB::connection()->getDriverName() === 'pgsql';

        if ($pg) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('match_embeddings', function (Blueprint $table) {
            $table->foreignId('match_id')->primary()->constrained('matches')->cascadeOnDelete();
            // The human-readable card that was embedded — handed to the model as evidence,
            // and lets us re-embed without rebuilding it.
            $table->text('document');
            $table->timestamp('embedded_at');
        });

        if ($pg) {
            DB::statement('ALTER TABLE match_embeddings ADD COLUMN embedding vector('.$dimensions.')');
            // No ANN index on purpose. An ivfflat/hnsw index is an APPROXIMATE search:
            // on a tiny corpus its probes can miss the populated list and return zero
            // rows (it did — the footgun that motivated this note). A sequential scan
            // with `<=>` is exact and instant at this scale. Add an ivfflat index
            // (tuned lists + ivfflat.probes) once the corpus is large enough for a full
            // scan to hurt — the earned upgrade, same spirit as list→Streams.
        } else {
            // sqlite (tests): a plain text column stands in; the fake retriever never reads it.
            Schema::table('match_embeddings', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_embeddings');
    }
};
