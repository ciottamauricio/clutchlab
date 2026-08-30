<?php

namespace App\Services\Dora;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\ParseEvent;
use Carbon\CarbonInterface;

// The five delivery metrics, each computed from rows this system recorded itself.
//
// Every metric returns null when its window holds no data, and the bucket goes null with
// it. That is deliberate: a rate over zero deploys is not "elite", it is unmeasured, and
// a dashboard that renders 0% CFR for a service that never shipped is worse than one
// admitting it has nothing to show.
class MetricsCalculator
{
    public function __construct(private readonly int $sloMs, private readonly float $sloTarget) {}

    /** Successful production deploys per day across the window. */
    public function deploymentFrequency(CarbonInterface $from, CarbonInterface $to): array
    {
        $deploys = Deployment::query()
            ->where('status', 'success')
            ->whereBetween('deploy_finished_at', [$from, $to])
            ->count();

        $days = max(1, (int) round($from->diffInDays($to)));

        if ($deploys === 0) {
            return ['value' => null, 'unit' => 'per_day', 'bucket' => null, 'sample' => 0];
        }

        $perDay = $deploys / $days;

        return [
            'value' => round($perDay, 2),
            'unit' => 'per_day',
            'bucket' => $this->frequencyBucket($perDay),
            'sample' => $deploys,
        ];
    }

    // Median, not mean: one commit that sat on a branch for a month would drag an average
    // past the point where it describes anything real.
    public function medianLeadTime(CarbonInterface $from, CarbonInterface $to): array
    {
        $seconds = Deployment::query()
            ->where('status', 'success')
            ->whereBetween('deploy_finished_at', [$from, $to])
            ->get(['commit_authored_at', 'deploy_finished_at'])
            ->map(fn (Deployment $d) => $d->leadTimeSeconds())
            ->all();

        $median = $this->median($seconds);

        return [
            'value_seconds' => $median,
            'bucket' => $median === null ? null : $this->leadTimeBucket($median),
            'sample' => count($seconds),
        ];
    }

    // Denominator is successful deploys: a deploy that failed in the pipeline never
    // reached users, so counting it as a "change failure" would double-punish a pipeline
    // that caught its own problem.
    public function changeFailureRate(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = Deployment::query()
            ->where('status', 'success')
            ->whereBetween('deploy_finished_at', [$from, $to]);

        $total = (clone $base)->count();

        if ($total === 0) {
            return ['value' => null, 'bucket' => null, 'sample' => 0];
        }

        $failed = (clone $base)->where('caused_failure', true)->count();
        $rate = $failed / $total;

        return [
            'value' => round($rate, 4),
            'bucket' => $this->changeFailureBucket($rate),
            'sample' => $total,
        ];
    }

    // Open incidents are excluded: an outage still running has no restore time yet, and
    // treating "now" as its end would make MTTR improve every time you looked at it.
    public function medianTimeToRestore(CarbonInterface $from, CarbonInterface $to): array
    {
        $seconds = Incident::query()
            ->whereNotNull('resolved_at')
            ->whereBetween('opened_at', [$from, $to])
            ->get(['opened_at', 'resolved_at'])
            ->map(fn (Incident $i) => $i->restoreSeconds())
            ->all();

        $median = $this->median($seconds);

        return [
            'value_seconds' => $median,
            'bucket' => $median === null ? null : $this->restoreBucket($median),
            'sample' => count($seconds),
        ];
    }

    // The one metric measured from the product rather than the pipeline: did the upload
    // the user made actually turn into stats, quickly enough to feel immediate.
    public function reliability(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = ParseEvent::query()->whereBetween('created_at', [$from, $to]);

        $total = (clone $base)->count();

        if ($total === 0) {
            return [
                'value' => null,
                'target' => $this->sloTarget,
                'met' => null,
                'sample' => 0,
            ];
        }

        $good = (clone $base)
            ->where('status', 'success')
            ->where('duration_ms', '<=', $this->sloMs)
            ->count();

        $ratio = $good / $total;

        return [
            'value' => round($ratio, 4),
            'target' => $this->sloTarget,
            'met' => $ratio >= $this->sloTarget,
            'sample' => $total,
        ];
    }

    /** Per-day deploy and failure counts, for the dashboard's trend chart. */
    public function trend(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Deployment::query()
            ->whereBetween('deploy_finished_at', [$from, $to])
            ->get(['deploy_finished_at', 'status', 'caused_failure']);

        $byDay = [];
        foreach ($rows as $row) {
            $day = $row->deploy_finished_at->toDateString();
            $byDay[$day] ??= ['date' => $day, 'deploys' => 0, 'failures' => 0];

            if ($row->status === 'success') {
                $byDay[$day]['deploys']++;
            }
            // A failure is either a deploy that broke in the pipeline or one later blamed
            // for an incident — both are things the chart should show.
            if ($row->status === 'failed' || $row->caused_failure) {
                $byDay[$day]['failures']++;
            }
        }

        ksort($byDay);

        return array_values($byDay);
    }

    /** @param array<int|null> $values */
    private function median(array $values): ?int
    {
        $values = array_values(array_filter($values, fn ($v) => $v !== null));
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 1
            ? (int) $values[$mid]
            : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    // Thresholds follow the DORA / State of DevOps bands. They are guide rails: the
    // boundaries are approximate in the research too, and a project this size will sit in
    // one band for months at a time.
    private function frequencyBucket(float $perDay): string
    {
        return match (true) {
            $perDay >= 1.0 => 'elite',        // on-demand / daily or better
            $perDay >= 1 / 7 => 'high',       // weekly
            $perDay >= 1 / 30 => 'medium',    // monthly
            default => 'low',
        };
    }

    private function leadTimeBucket(int $seconds): string
    {
        return match (true) {
            $seconds < 86400 => 'elite',      // under a day
            $seconds < 604800 => 'high',      // under a week
            $seconds < 2592000 => 'medium',   // under a month
            default => 'low',
        };
    }

    // The research puts elite/high/medium all at 0–15%; only above 15% separates out. The
    // band is kept flat here rather than invented finer, so the number isn't given more
    // precision than the source has.
    private function changeFailureBucket(float $rate): string
    {
        return $rate <= 0.15 ? 'elite' : 'low';
    }

    private function restoreBucket(int $seconds): string
    {
        return match (true) {
            $seconds < 3600 => 'elite',       // under an hour
            $seconds < 86400 => 'high',       // under a day
            $seconds < 604800 => 'medium',    // under a week
            default => 'low',
        };
    }
}
