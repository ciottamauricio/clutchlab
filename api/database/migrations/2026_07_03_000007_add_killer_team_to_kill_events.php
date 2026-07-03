<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The killer's team (their stable, whole-match side), written by the worker. Lets kills
// be filtered by team rather than by per-kill side, which flips at the half-time swap.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->string('killer_team')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->dropColumn('killer_team');
        });
    }
};
