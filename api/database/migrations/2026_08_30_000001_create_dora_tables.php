<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The DORA measurement tables. All three live in `public` (the api's schema): the api is
// the only writer, even for parse telemetry — the worker reports parse outcomes as facts
// on clutch_events and the api records them, the same rule the matches row already
// follows since the DB split.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->string('service');                 // api | worker | web
            $table->string('environment')->default('production');
            $table->string('commit_sha', 40);
            // Authored time comes from the deploying commit, not from row insertion:
            // lead time is measured from when the work was written, not when it shipped.
            $table->timestamp('commit_authored_at');
            $table->timestamp('deploy_started_at');
            $table->timestamp('deploy_finished_at');
            $table->string('status');                  // success | failed
            // Set when an incident is linked to this deploy; the CFR numerator.
            $table->boolean('caused_failure')->default(false);
            $table->string('actions_run_id')->nullable();
            $table->timestamps();

            // Every window query filters on the finish time, and CFR/frequency slice by
            // service on top of it.
            $table->index(['deploy_finished_at']);
            $table->index(['service', 'deploy_finished_at']);
            // A workflow that retries its ingestion call must not inflate the deploy
            // count — one run of one service records at most one row.
            $table->unique(['service', 'actions_run_id']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('service')->nullable();     // null = spans services
            // The deploy blamed for it. Nullable: not every incident comes from a deploy,
            // and the deploy may be outside retention.
            $table->foreignId('deployment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable(); // null while open
            $table->text('description');
            $table->timestamps();

            $table->index(['opened_at']);
        });

        Schema::create('parse_events', function (Blueprint $table) {
            $table->id();
            // Not a foreign key: telemetry outlives the match it describes. Deleting a
            // demo must not rewrite delivery history.
            $table->unsignedBigInteger('match_id')->nullable();
            $table->string('status');                  // success | failed
            $table->unsignedInteger('duration_ms')->nullable(); // null when the worker sent none
            $table->string('error_code')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parse_events');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('deployments');
    }
};
