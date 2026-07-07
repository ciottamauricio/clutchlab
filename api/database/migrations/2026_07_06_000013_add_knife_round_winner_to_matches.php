<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Stable side ("CT"/"T") of the team that won the pre-match knife round; null when
            // the demo had none. The knife round's kills are excluded from the stats.
            $table->string('knife_round_winner', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('knife_round_winner');
        });
    }
};
