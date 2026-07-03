<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Victim world coordinates at death, written by the Go worker. Feeds the kill heatmap
// (api/docs/domains/heatmap.md). Nullable: kills without a known position are skipped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->double('victim_x')->nullable();
            $table->double('victim_y')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->dropColumn(['victim_x', 'victim_y']);
        });
    }
};
