<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('demo_key')->unique();

            // Lifecycle codes, never sentences: queued -> parsing -> parsed | failed.
            $table->string('status')->default('queued')->index();
            $table->string('error_code')->nullable();

            // Filled in by the Go worker once the demo is parsed.
            $table->string('map_name')->nullable();
            $table->unsignedInteger('score_ct')->nullable();
            $table->unsignedInteger('score_t')->nullable();
            $table->string('ct_name')->nullable();
            $table->string('t_name')->nullable();
            $table->unsignedInteger('total_rounds')->nullable();
            $table->timestamp('parsed_at')->nullable();

            $table->timestamps();
        });

        Schema::create('match_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();

            // 64-bit SteamID kept as text so JS never loses precision on it.
            $table->string('steam_id');
            $table->string('name');
            $table->string('team_side')->nullable();
            $table->unsignedInteger('kills')->default(0);
            $table->unsignedInteger('deaths')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('headshots')->default(0);

            $table->unique(['match_id', 'steam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_player_stats');
        Schema::dropIfExists('matches');
    }
};
