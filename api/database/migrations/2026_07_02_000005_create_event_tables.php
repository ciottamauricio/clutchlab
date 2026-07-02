<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Source of truth for the search read model. Written by the Go worker after a parse,
// then projected into Meilisearch (see api/docs/domains/search.md).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kill_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('map')->nullable();
            $table->unsignedInteger('round');
            $table->string('killer_steam_id')->nullable();
            $table->string('killer_name')->nullable();
            $table->string('victim_steam_id')->nullable();
            $table->string('victim_name')->nullable();
            $table->string('assister_name')->nullable();
            $table->string('weapon')->nullable();
            $table->boolean('headshot')->default(false);
            $table->boolean('opening')->default(false);
            $table->string('side')->nullable();

            $table->index(['match_id']);
            $table->index(['owner_id']);
        });

        Schema::create('round_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('map')->nullable();
            $table->unsignedInteger('round');
            $table->string('winner')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedInteger('ct_alive')->default(0);
            $table->unsignedInteger('t_alive')->default(0);
            $table->string('ct_buy')->nullable();
            $table->string('t_buy')->nullable();

            $table->index(['match_id']);
            $table->index(['owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kill_events');
        Schema::dropIfExists('round_events');
    }
};
