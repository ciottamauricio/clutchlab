<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The in-game roster: which demo players (by SteamID64) "belong" to a team, so
        // their kill_events can be aggregated as the team's stats. Distinct from team_user
        // (app-login membership + roles) — a rostered player usually has no account.
        Schema::create('team_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('steam_id');
            // Optional label the owner sets (the demo name is unstable — clan tags, emojis).
            $table->string('nickname')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'steam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_players');
    }
};
