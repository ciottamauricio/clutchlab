<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Phase 1, part 2: two DB roles scoped by schema, so the ownership line is enforced by
// Postgres, not just convention.
//   - clutch_app    → owns the transactional core (public), no rights on analytics.
//   - clutch_worker → writes analytics.*, SELECT-only on public.matches (it still reads
//                     user_id / original_filename until phase 2 removes even that).
// Migrations still run as the bootstrap superuser (DB_USERNAME); the services connect as
// these scoped roles. Idempotent so re-running the suite is safe. Postgres-only.
return new class extends Migration
{
    private const ANALYTICS = ['match_player_stats', 'kill_events', 'round_events'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // sqlite tests: no roles
        }

        $appPass = env('DB_APP_PASSWORD', 'app_secret');
        $workerPass = env('DB_WORKER_PASSWORD', 'worker_secret');

        $this->ensureRole('clutch_app', $appPass);
        $this->ensureRole('clutch_worker', $workerPass);

        // clutch_app: full run of the transactional core (public), nothing in analytics.
        DB::statement('GRANT USAGE ON SCHEMA public TO clutch_app');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO clutch_app');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO clutch_app');
        // Future tables in public inherit the grant.
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO clutch_app');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO clutch_app');

        // clutch_worker: owns analytics; read-only on public.matches only (narrow coupling,
        // gone in phase 2). Deliberately NO write on public — proving the wall is the point.
        DB::statement('GRANT USAGE ON SCHEMA analytics TO clutch_worker');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA analytics TO clutch_worker');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA analytics TO clutch_worker');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA analytics GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO clutch_worker');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA analytics GRANT USAGE, SELECT ON SEQUENCES TO clutch_worker');
        DB::statement('GRANT USAGE ON SCHEMA public TO clutch_worker');
        DB::statement('GRANT SELECT ON public.matches TO clutch_worker');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Revoke default privileges first (they don't drop with the role), then the role.
        foreach (['clutch_app' => 'public', 'clutch_worker' => 'analytics'] as $role => $schema) {
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA {$schema} REVOKE ALL ON TABLES FROM {$role}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA {$schema} REVOKE ALL ON SEQUENCES FROM {$role}");
            DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA {$schema} FROM {$role}");
            DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA {$schema} FROM {$role}");
            DB::statement("REVOKE ALL ON SCHEMA {$schema} FROM {$role}");
        }
        DB::statement('REVOKE ALL ON public.matches FROM clutch_worker');
        DB::statement('REVOKE USAGE ON SCHEMA public FROM clutch_worker');
        DB::statement('DROP ROLE IF EXISTS clutch_app');
        DB::statement('DROP ROLE IF EXISTS clutch_worker');
    }

    // CREATE ROLE has no IF NOT EXISTS; do it conditionally so re-runs don't error.
    private function ensureRole(string $role, string $password): void
    {
        $exists = DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]);
        $pass = str_replace("'", "''", $password);
        if ($exists) {
            DB::statement("ALTER ROLE {$role} WITH LOGIN PASSWORD '{$pass}'");
        } else {
            DB::statement("CREATE ROLE {$role} WITH LOGIN PASSWORD '{$pass}'");
        }
    }
};
