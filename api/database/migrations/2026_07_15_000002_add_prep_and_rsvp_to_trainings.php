<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pre-class homework: the backend stores the MEANING (map + nade type); the
        // study-site URL is derived in the frontend (docs/domains/trainings.md).
        Schema::create('training_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('map', 32);
            $table->string('nade_type', 16); // smoke | molotov | flashbang | he
            $table->timestamp('done_at')->nullable(); // set by the assignee only
            $table->timestamps();

            $table->unique(['training_session_id', 'user_id', 'map', 'nade_type'], 'training_assignments_unique');
        });

        // RSVP on the roster: null = no answer yet, 'in' / 'out' answered by the player.
        Schema::table('training_session_user', function (Blueprint $table) {
            $table->string('rsvp', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('training_session_user', fn (Blueprint $table) => $table->dropColumn('rsvp'));
        Schema::dropIfExists('training_assignments');
    }
};
