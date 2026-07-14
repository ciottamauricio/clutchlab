<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // The scheduler; sessions outlive the account that created them.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at'); // UTC; the frontend localizes
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamp('canceled_at')->nullable(); // cancellation without a status machine
            $table->timestamps();

            $table->index(['team_id', 'scheduled_at']);
        });

        Schema::create('training_session_tactic', function (Blueprint $table) {
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tactic_id')->constrained()->cascadeOnDelete();
            $table->unique(['training_session_id', 'tactic_id']);
        });

        Schema::create('training_session_user', function (Blueprint $table) {
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['training_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_session_user');
        Schema::dropIfExists('training_session_tactic');
        Schema::dropIfExists('training_sessions');
    }
};
