<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // The team this match belongs to; every member of it can see the match. Nullable:
            // a match with no team is private to its uploader (user_id). nullOnDelete so
            // deleting a team un-shares its matches rather than destroying them.
            // (Index added separately: chaining ->index() here poisons the FK name to "1".)
            $table->foreignId('team_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
