<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Each platform names its downloads its own way. Uploads after this migration carry an
    // explicit choice, so these patterns only label the rows that predate it; a name that
    // matches none stays null (unknown) rather than being guessed as `other`.
    private const FILENAME_PATTERNS = [
        'gamersclub' => '/^\d{4}-\d{2}-\d{2}__\d{4}__/',
        'steam' => '/^match730_/',
        'faceit' => '/^1-[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}/i',
    ];

    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('platform', 20)->nullable()->after('team_id');
        });

        foreach (DB::table('matches')->select('id', 'original_filename')->get() as $row) {
            foreach (self::FILENAME_PATTERNS as $platform => $pattern) {
                if (preg_match($pattern, (string) $row->original_filename)) {
                    DB::table('matches')->where('id', $row->id)->update(['platform' => $platform]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
