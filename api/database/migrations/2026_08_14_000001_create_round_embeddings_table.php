<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Semantic read model at round granularity — match_embeddings' twin, one level down.
// Same shape, same Postgres-only caveat: sqlite (tests) gets a text stand-in and the
// retriever is faked. Rebuildable from analytics.round_events at any time.
return new class extends Migration
{
    public function up(): void
    {
        $dimensions = (int) config('clutch.embed.dimensions', 256);
        $pg = DB::connection()->getDriverName() === 'pgsql';

        Schema::create('round_embeddings', function (Blueprint $table) {
            // (match_id, round) is the identity: rounds are only unique within a match.
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->text('document');
            $table->timestamp('embedded_at');

            $table->primary(['match_id', 'round']);
        });

        if ($pg) {
            DB::statement('ALTER TABLE round_embeddings ADD COLUMN embedding vector('.$dimensions.')');
            // Still no ANN index — see match_embeddings for why. This corpus is ~24x
            // larger (roughly 600 rounds against 25 matches), which is the scale where
            // that decision earns a re-measurement rather than an assumption: check the
            // query time before reaching for ivfflat, and tune probes if you add it.
        } else {
            Schema::table('round_embeddings', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('round_embeddings');
    }
};
