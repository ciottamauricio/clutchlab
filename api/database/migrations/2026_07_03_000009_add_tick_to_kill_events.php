<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Demo tick of each kill + the match tick rate, so the UI can build a
// `demo_gototick` command to jump the demo to that moment (api/docs/domains/heatmap.md).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->unsignedBigInteger('tick')->nullable();
        });
        Schema::table('matches', function (Blueprint $table) {
            $table->float('tick_rate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->dropColumn('tick');
        });
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('tick_rate');
        });
    }
};
