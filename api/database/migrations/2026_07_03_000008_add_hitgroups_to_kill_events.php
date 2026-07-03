<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-kill body-zone damage breakdown (head/chest/stomach/arms/legs), written by the
// worker from PlayerHurt events. Feeds the body hitgroup map (api/docs/domains/heatmap.md).
// Nullable: knife/bomb/fall deaths carry no tracked hits.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->jsonb('hitgroups')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->dropColumn('hitgroups');
        });
    }
};
