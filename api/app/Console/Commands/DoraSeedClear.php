<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\ParseEvent;
use Database\Seeders\DoraDemoSeeder;
use Illuminate\Console\Command;

// Removes the demo data and nothing else. Run this before the first real deploy lands,
// so the dashboard stops mixing invented history with measured delivery.
class DoraSeedClear extends Command
{
    protected $signature = 'dora:seed-clear';

    protected $description = 'Delete seeded DORA demo data, leaving CI-reported rows untouched';

    public function handle(): int
    {
        $seeded = Deployment::where('actions_run_id', 'like', DoraDemoSeeder::MARKER.'%');
        $ids = (clone $seeded)->pluck('id');

        $incidents = Incident::whereIn('deployment_id', $ids)->delete();
        $deployments = (clone $seeded)->delete();
        // Seeded telemetry is exactly the rows describing no real match.
        $events = ParseEvent::whereNull('match_id')->delete();

        $this->info("Removed {$deployments} deployments, {$incidents} incidents, {$events} parse events.");

        $remaining = Deployment::count();
        if ($remaining > 0) {
            $this->line("{$remaining} real deployment(s) kept.");
        }

        return self::SUCCESS;
    }
}
