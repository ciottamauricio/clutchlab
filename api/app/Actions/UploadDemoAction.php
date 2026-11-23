<?php

namespace App\Actions;

use App\Contracts\DemoStorage;
use App\Contracts\DemoUnpacker;
use App\Contracts\ParseQueue;
use App\Models\GameMatch;
use App\Models\User;
use App\Storage\DemoUnpackFailed;
use App\Telemetry\Tracing;
use Carbon\CarbonInterface;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use OpenTelemetry\API\Trace\SpanKind;

class UploadDemoAction
{
    public function __construct(
        private DemoStorage $storage,
        private DemoUnpacker $unpacker,
        private ParseQueue $queue,
        private Tracing $tracing,
    ) {}

    public function execute(
        UploadedFile $file,
        User $owner,
        string $platform,
        ?string $externalId = null,
        ?CarbonInterface $playedAt = null,
        ?int $teamId = null,
        ?string $contentHash = null,
    ): GameMatch {
        // The root span for the whole upload → parse → notify flow. Store, create, and
        // enqueue run inside it, and push() injects this span's traceparent into the job
        // so the worker's parse becomes a child — one waterfall across three services.
        $span = $this->tracing->tracer()->spanBuilder('upload_demo')
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->startSpan();
        $scope = $span->activate();

        try {
            $key = $this->storePlain($file);
            $filename = $file->getClientOriginalName();

            $match = $owner->matches()->create([
                'original_filename' => $filename,
                'demo_key' => $key,
                'content_hash' => $contentHash,
                'status' => 'queued',
                'team_id' => $teamId,
                'platform' => $platform,
                'external_id' => $externalId,
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

    // Storage only ever holds a plain .dem (invariant 2), so a packed upload is unpacked first and
    // only the unpacked demo is stored. Unpack failures reach the client as a field error on `demo`,
    // like the other demo.* codes.
    private function storePlain(UploadedFile $file): string
    {
        if (! $this->unpacker->handles($file->getRealPath())) {
            return $this->storage->store($file);
        }

        try {
            $plain = $this->unpacker->unpack($file->getRealPath());
        } catch (DemoUnpackFailed $e) {
            throw ValidationException::withMessages(['demo' => $e->getMessage()]);
        }

        try {
            return $this->storage->store(new File($plain));
        } finally {
            @unlink($plain);
        }
    }
}
