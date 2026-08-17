<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The third semantic read model: the repo's own markdown, chunked by heading. Same shape
// as match_embeddings and round_embeddings, same Postgres-only caveat (sqlite gets a text
// stand-in and the retriever is faked in tests).
//
// Unlike its two siblings this table has no match_id and no foreign key — a design doc
// belongs to no match. It is rebuildable from the working tree at any time, which makes it
// the most disposable projection of the three: `docs:embed` reads files, not the database.
return new class extends Migration
{
    public function up(): void
    {
        $dimensions = (int) config('clutch.embed.dimensions', 256);
        $pg = DB::connection()->getDriverName() === 'pgsql';

        Schema::create('doc_embeddings', function (Blueprint $table) {
            // (path, heading) is the identity: a heading is only unique within its file.
            // Both are short enough to key on, and re-embedding the same section must
            // replace it rather than accumulate copies.
            $table->string('path', 255);
            $table->string('heading', 255);
            $table->text('document');
            $table->timestamp('embedded_at');

            $table->primary(['path', 'heading']);
        });

        if ($pg) {
            DB::statement('ALTER TABLE doc_embeddings ADD COLUMN embedding vector('.$dimensions.')');
            // No ANN index, for the third time and the same reason: this corpus is the
            // SMALLEST of the three (~200 chunks against 645 rounds), and 645 rounds scan
            // exactly in 2.15ms. Nothing here justifies ivfflat.
        } else {
            Schema::table('doc_embeddings', function (Blueprint $table) {
                $table->text('embedding')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_embeddings');
    }
};
