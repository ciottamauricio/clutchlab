<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Clutch size (1vN) a kill was part of, written by the worker: the killer was the last
// player alive on their team. 0 = not a clutch kill. Feeds the clutch filter.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('clutch')->default(0);
            $table->index(['clutch']);
        });
    }

    public function down(): void
    {
        Schema::table('kill_events', function (Blueprint $table) {
            $table->dropIndex(['clutch']);
            $table->dropColumn('clutch');
        });
    }
};
