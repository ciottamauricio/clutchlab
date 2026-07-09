<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The catalog of abilities. `scope` says which authority axis an ability lives on:
        // 'app' abilities are granted to a global role (member/admin); 'team' abilities are
        // granted to a team role (owner/igl/player/coach) and resolved against a match's/team's
        // context. Admin bypasses everything via Gate::before, so it needs no grant rows.
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('scope'); // 'app' | 'team'
            $table->string('label');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // App-scope grants: a global role has an app ability (e.g. member → awards.view).
        Schema::create('global_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role'); // App\Enums\UserRole value
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role', 'permission_id']);
        });

        // Team-scope grants: a team role has a team ability (e.g. igl → match.delete). Whether a
        // given user has it depends on their role in the relevant team, resolved at check time.
        Schema::create('team_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role'); // owner | igl | player | coach
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_role_permissions');
        Schema::dropIfExists('global_role_permissions');
        Schema::dropIfExists('permissions');
    }
};
