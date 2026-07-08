<?php

use App\Models\GameMatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // When the match was actually played (UTC), from the demo filename. Nullable: not
            // every filename carries it. Promoted from a read-time parse to a column so it's
            // filterable/sortable (e.g. the team stat board's date range).
            $table->timestamp('played_at')->nullable()->after('parsed_at');
        });

        // Backfill existing rows from their filenames, using the same parser the app writes with.
        foreach (DB::table('matches')->select('id', 'original_filename')->get() as $row) {
            $played = GameMatch::playedAtFromFilename($row->original_filename);
            if ($played) {
                DB::table('matches')->where('id', $row->id)->update(['played_at' => $played]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('played_at');
        });
    }
};
