<?php

namespace App\PlatformSync\Domain;

use Carbon\CarbonImmutable;

// One row of the platform panel: a match from the user's history, scored from the user's team and
// marked with the Clutchlab match filed under it, if the user can see one.
final readonly class MatchRow
{
    /**
     * @param  array{own: ?int, opponent: ?int}|null  $score
     * @param  'win'|'loss'|null  $result
     */
    private function __construct(
        public PlatformMatchId $id,
        public ?CarbonImmutable $startedAt,
        public ?string $map,
        public ?string $competition,
        public ?array $score,
        public ?string $result,
        public ?string $roomUrl,
        public ?InClutchlab $inClutchlab,
    ) {}

    /** @param  list<InClutchlab>  $inClutchlab  the Clutchlab matches the user can see filed under it */
    public static function of(PlayedMatch $match, string $playerId, int $userId, array $inClutchlab, ?string $map): self
    {
        // No team lists the user: no score is claimed for them.
        $own = $match->teamOf($playerId);

        return new self(
            $match->id,
            $match->startedAt !== null ? CarbonImmutable::createFromTimestampUTC($match->startedAt) : null,
            $map,
            $match->competition,
            $own !== null ? ['own' => $match->team($own)->score, 'opponent' => $match->opponentOf($own)->score] : null,
            $own !== null && $match->winner !== null ? ($match->winner === $own ? 'win' : 'loss') : null,
            $match->roomUrl,
            self::shown($inClutchlab, $userId),
        );
    }

    // The user's own upload wins over a teammate's; among teammates', the earliest.
    private static function shown(array $candidates, int $userId): ?InClutchlab
    {
        usort($candidates, fn (InClutchlab $a, InClutchlab $b) => [$a->uploaderId !== $userId, $a->matchId] <=> [$b->uploaderId !== $userId, $b->matchId]);

        return $candidates[0] ?? null;
    }
}
