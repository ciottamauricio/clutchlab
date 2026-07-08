<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Platform-wide role, distinct from the per-team role on team_user.
            $table->string('role')->default('member');

            // The account's own SteamID64, admin-assigned and optional. Links a login to
            // its demo stats. String (never a JS-unsafe bigint); unique — one account per id.
            $table->string('steam_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['steam_id']);
            $table->dropColumn(['role', 'steam_id']);
        });
    }
};
