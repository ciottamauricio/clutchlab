<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Public-ish profile a user maintains about themselves.
            $table->string('player_role')->nullable();   // in-game role: awper, rifler, igl, …
            $table->text('bio')->nullable();

            // Setup / gear — free text per item ("Logitech G Pro X", "i5-13600K / RTX 4070", …).
            $table->string('pc')->nullable();
            $table->string('mouse')->nullable();
            $table->string('keyboard')->nullable();
            $table->string('headset')->nullable();
            $table->string('monitor')->nullable();
            $table->string('mousepad')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['player_role', 'bio', 'pc', 'mouse', 'keyboard', 'headset', 'monitor', 'mousepad']);
        });
    }
};
