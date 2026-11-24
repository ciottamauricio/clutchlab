<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The FACEIT account found for a user from their profile's Steam ID. Kept out of `users`:
        // it is an integration's cache of who the user is on FACEIT, droppable with the integration.
        Schema::create('faceit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // The Steam ID this account was found for: when the profile's Steam ID no longer
            // matches it, the account is looked up again.
            $table->string('steam_id', 32);
            $table->string('player_id', 64);
            $table->string('nickname', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faceit_accounts');
    }
};
