<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Phase 1 of the shared-DB split (docs/plans/split-the-database.md): move the worker-owned
// analytics tables into their own `analytics` schema, so ownership can be enforced by
// per-service DB roles (next migration). This is a metadata rename — no rows move, no
// reparse. Postgres-only; the sqlite test DB has no schemas and keeps everything in the
// default schema (the search_path trick below is a no-op there).
return new class extends Migration
{
    private const TABLES = ['match_player_stats', 'kill_events', 'round_events'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // sqlite (tests): no schemas; tables stay in the default schema
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS analytics');

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE IF EXISTS {$table} SET SCHEMA analytics");
        }

        // Both services keep using unqualified names (match_player_stats, not
        // analytics.match_player_stats): the DB's search_path resolves them. Set it on the
        // database so every connection inherits it, regardless of role.
        $db = DB::connection()->getDatabaseName();
        DB::statement("ALTER DATABASE \"{$db}\" SET search_path TO public, analytics");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE IF EXISTS analytics.{$table} SET SCHEMA public");
        }

        $db = DB::connection()->getDatabaseName();
        DB::statement("ALTER DATABASE \"{$db}\" SET search_path TO public");
        DB::statement('DROP SCHEMA IF EXISTS analytics');
    }
};
