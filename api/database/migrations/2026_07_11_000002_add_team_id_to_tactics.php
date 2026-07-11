<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tactics', function (Blueprint $table) {
            // Deleting a team un-shares its tactics (sets null), it doesn't delete them —
            // same rule as matches.team_id.
            $table->foreignId('team_id')->nullable()->after('user_id')->index()
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tactics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
