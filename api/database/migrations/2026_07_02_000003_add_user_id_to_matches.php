<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // The uploader/owner. Nullable so pre-auth rows survive (they belong to no one
            // and are therefore invisible once listings are owner-scoped).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->index();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
