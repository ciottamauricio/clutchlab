<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Match duration in seconds, written by the worker: the span of actual play (first round's
// freezetime-end to the last round's end), so warmup and any post-game time don't inflate it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->float('duration_seconds')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
