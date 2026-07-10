<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A demo's stable identity is its content, not its (random) storage key or its
        // client-supplied filename. Hash it once at upload so the same file can't be
        // uploaded twice by the same user. Nullable: legacy rows predate the hash.
        Schema::table('matches', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('demo_key');
        });

        // Scoped per uploader: re-uploading *your own* demo is the duplicate we reject; two
        // different users each holding the same demo is allowed.
        Schema::table('matches', function (Blueprint $table) {
            $table->unique(['user_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};
