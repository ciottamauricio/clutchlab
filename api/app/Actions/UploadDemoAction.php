<?php

namespace App\Actions;

use App\Contracts\DemoStorage;
use App\Contracts\ParseQueue;
use App\Models\GameMatch;
use App\Models\User;
use App\Telemetry\Tracing;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use OpenTelemetry\API\Trace\SpanKind;

class UploadDemoAction
{
    public function __construct(
        private DemoStorage $storage,
        private ParseQueue $queue,
        private Tracing $tracing,
    ) {}

    public function execute(UploadedFile $file, User $owner, string $platform, ?CarbonInterface $playedAt = null, ?int $teamId = null, ?string $contentHash = null): GameMatch
    {
        // The root span for the whole upload → parse → notify flow. Store, create, and
        // enqueue run inside it, and push() injects this span's traceparent into the job
        // so the worker's parse becomes a child — one waterfall across three services.
        $span = $this->tracing->tracer()->spanBuilder('upload_demo')
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->startSpan();
        $scope = $span->activate();

        try {
            $key = $this->storage->store($file);
            $filename = $file->getClientOriginalName();

            $match = $owner->matches()->create([
                'original_filename' => $filename,
                'demo_key' => $key,
                'content_hash' => $contentHash,
                'status' => 'queued',
                'team_id' => $teamId,
                'platform' => $platform,
                // Eloquent writes a datetime's wall clock as-is, so normalize to UTC first —
                // the browser sends the player's local time with its offset.
                'played_at' => $playedAt?->toImmutable()->utc() ?? GameMatch::playedAtFromFilename($filename),
            ]);

            $span->setAttribute('match_id', $match->id);
            // owner_id + filename ride the job so the worker never reads the matches table.
            $this->queue->push($match->id, $key, $owner->id, $filename);

            return $match;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
