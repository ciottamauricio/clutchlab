<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // FACEIT names its downloads after the match (`1-<uuid>-1-1.dem[.zst]`), so the FACEIT matches
    // that predate this column can be filed from their names. Frozen here: after this migration the
    // id is stated by the upload, never read from a name.
    private const FACEIT_NAME = '/^(1-[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12})/i';

    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('external_id', 64)->nullable()->after('platform');
            // One row per uploader per platform match: the .dem and the .dem.zst of the same
            // FACEIT match have different bytes, so content_hash alone lets both in.
            $table->unique(['user_id', 'platform', 'external_id']);
        });

        $rows = DB::table('matches')->where('platform', 'faceit')->whereNull('external_id')
            ->select('id', 'original_filename')->get();
        foreach ($rows as $row) {
            if (preg_match(self::FACEIT_NAME, (string) $row->original_filename, $m)) {
                DB::table('matches')->where('id', $row->id)->update(['external_id' => strtolower($m[1])]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'platform', 'external_id']);
            $table->dropColumn('external_id');
        });
    }
};
