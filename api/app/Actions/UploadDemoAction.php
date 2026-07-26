<?php

namespace App\Actions;

use App\Contracts\DemoStorage;
use App\Contracts\ParseQueue;
use App\Models\GameMatch;
use App\Models\User;
use App\Telemetry\Tracing;
use Illuminate\Http\UploadedFile;
use OpenTelemetry\API\Trace\SpanKind;

class UploadDemoAction
{
    public function __construct(
        private DemoStorage $storage,
        private ParseQueue $queue,
        private Tracing $tracing,
    ) {}

    public function execute(UploadedFile $file, User $owner, ?int $teamId = null, ?string $contentHash = null): GameMatch
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
                'played_at' => GameMatch::playedAtFromFilename($filename),
            ]);

            $span->setAttribute('match_id', $match->id);
            $this->queue->push($match->id, $key);

            return $match;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
