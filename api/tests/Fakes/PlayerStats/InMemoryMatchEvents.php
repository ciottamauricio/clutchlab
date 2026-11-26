<?php

namespace Tests\Fakes\PlayerStats;

use App\PlayerStats\Domain\AwardKey;
use App\PlayerStats\Domain\Clutches;
use App\PlayerStats\Domain\HitGroups;
use App\PlayerStats\Domain\Kill;
use App\PlayerStats\Domain\PlayerCounts;
use App\PlayerStats\Domain\PlayerEntry;
use App\PlayerStats\Domain\ScoreboardLine;
use App\PlayerStats\Domain\SideCounts;
use App\PlayerStats\Domain\WeaponKills;
use App\PlayerStats\Domain\Weapons;
use App\PlayerStats\Ports\MatchEvents;

// The worker's kill events and scoreboards in memory, counted by the domain's rules: this is the
// executable definition PostgresMatchEvents' SQL is held to (MatchEventsContractTest). Names sort
// bytewise here; Postgres sorts them by its collation, which the contract cases steer clear of.
class InMemoryMatchEvents implements MatchEvents
{
    /** @var list<Kill> */
    private array $kills = [];

    /** @var list<array{match: int, steamId: string, name: string, side: ?string, kills: int, deaths: int, assists: int, headshots: int}> */
    private array $scoreboards = [];

    public function kill(Kill $kill): void
    {
        $this->kills[] = $kill;
    }

    public function line(int $match, string $steamId, string $name, ?string $side, int $kills = 0, int $deaths = 0, int $assists = 0, int $headshots = 0): void
    {
        $this->scoreboards[] = compact('match', 'steamId', 'name', 'side', 'kills', 'deaths', 'assists', 'headshots');
    }

    public function counts(array $matchIds, ?array $only = null, ?string $map = null): array
    {
        $by = [];
        $add = function (string $id, string $field, int $n = 1) use (&$by) {
            $by['#'.$id][$field] = ($by['#'.$id][$field] ?? 0) + $n;
        };
        $kills = $this->killsIn($matchIds, $map);
        foreach ($kills as $k) {
            if ($only === null || in_array($k->killerId, $only, true)) {
                $add($k->killerId, 'kills');
                $add($k->killerId, 'headshots', (int) $k->headshot);
                $add($k->killerId, 'openingKills', (int) $k->opening);
                $add($k->killerId, 'ecoKills', (int) Weapons::isEco($k->weapon));
                $add($k->killerId, 'legKills', (int) HitGroups::legsDominant($k->hitgroups));
            }
            if ($only === null || in_array($k->victimId, $only, true)) {
                $add($k->victimId, 'deaths');
                $add($k->victimId, 'openingDeaths', (int) $k->opening);
            }
        }
        foreach ($by as $key => $c) {
            $id = substr($key, 1);
            $by[$key]['clutchRounds'] = isset($c['kills']) ? Clutches::count(array_values(array_filter($kills, fn (Kill $k) => $k->killerId === $id))) : 0;
        }
        ksort($by, SORT_STRING);

        return array_values(array_map(fn ($key, $c) => new PlayerCounts(substr($key, 1), ...$c), array_keys($by), $by));
    }

    public function weaponKills(array $matchIds, ?array $only = null, ?string $map = null): array
    {
        $by = [];
        foreach ($this->killsIn($matchIds, $map) as $k) {
            if (($k->weapon ?? '') !== '' && ($only === null || in_array($k->killerId, $only, true))) {
                $by['#'.$k->killerId][$k->weapon] = ($by['#'.$k->killerId][$k->weapon] ?? 0) + 1;
            }
        }
        ksort($by, SORT_STRING);

        $rows = [];
        foreach ($by as $key => $weapons) {
            ksort($weapons, SORT_STRING);
            foreach ($weapons as $weapon => $n) {
                $rows[] = new WeaponKills(substr($key, 1), (string) $weapon, $n);
            }
        }

        return $rows;
    }

    public function players(array $newestFirst, ?array $only = null): array
    {
        $rank = array_flip($newestFirst);
        $by = [];
        foreach ($this->scoreboards as $s) {
            if (! isset($rank[$s['match']]) || ($only !== null && ! in_array($s['steamId'], $only, true))) {
                continue;
            }
            $p = $by['#'.$s['steamId']] ?? ['name' => $s['name'], 'rank' => $rank[$s['match']], 'matches' => []];
            if ($rank[$s['match']] < $p['rank']) {
                $p = ['name' => $s['name'], 'rank' => $rank[$s['match']], 'matches' => $p['matches']];
            }
            $p['matches'][$s['match']] = true;
            $by['#'.$s['steamId']] = $p;
        }

        $players = array_map(fn ($key, $p) => new PlayerEntry(substr($key, 1), $p['name'], count($p['matches'])), array_keys($by), $by);
        usort($players, fn (PlayerEntry $a, PlayerEntry $b) => [$a->name, $a->steamId] <=> [$b->name, $b->steamId]);

        return $players;
    }

    public function scoreboard(array $matchIds, string $steamId): array
    {
        $lines = array_filter($this->scoreboards, fn ($s) => $s['steamId'] === $steamId && in_array($s['match'], $matchIds, true));
        usort($lines, fn ($a, $b) => $a['match'] <=> $b['match']);

        return array_map(fn ($s) => new ScoreboardLine($s['match'], $s['kills'], $s['deaths'], $s['side']), $lines);
    }

    public function sides(int $matchId): array
    {
        $sides = [];
        foreach ($this->scoreboards as $s) {
            if ($s['match'] === $matchId) {
                foreach (['kills', 'deaths', 'assists', 'headshots'] as $f) {
                    $sides[(string) $s['side']][$f] = ($sides[(string) $s['side']][$f] ?? 0) + $s[$f];
                }
            }
        }
        $kills = $this->killsIn([$matchId], null);
        foreach ($kills as $k) {
            if ($k->opening) {
                $sides[(string) $k->killerTeam]['openingKills'] = ($sides[(string) $k->killerTeam]['openingKills'] ?? 0) + 1;
            }
        }
        foreach (Clutches::fromKills($kills) as $clutch) {
            $team = (string) $clutch->kills[0]->killerTeam;
            $sides[$team]['clutchRounds'] = ($sides[$team]['clutchRounds'] ?? 0) + 1;
        }

        return array_map(fn ($c) => new SideCounts(...$c), $sides);
    }

    public function clutchKills(array $matchIds, ?string $clutcher = null): array
    {
        $kills = array_filter($this->killsIn($matchIds, null), fn (Kill $k) => $k->clutch > 0 && ($clutcher === null || $k->killerId === $clutcher));

        return $this->sorted($kills, fn (Kill $k) => [$k->matchId, $k->round, $k->tick]);
    }

    public function awardKills(array $matchIds, AwardKey $key, string $steamId, ?string $map = null, ?string $weapon = null): array
    {
        $mine = fn (Kill $k) => $k->killerId === $steamId;
        $keep = match ($key) {
            AwardKey::Legshots => fn (Kill $k) => $mine($k) && HitGroups::legsDominant($k->hitgroups),
            AwardKey::Eco => fn (Kill $k) => $mine($k) && Weapons::isEco($k->weapon),
            AwardKey::Clutches => fn (Kill $k) => $mine($k) && $k->clutch > 0,
            AwardKey::Headshot => fn (Kill $k) => $mine($k) && $k->headshot,
            AwardKey::OneTrick => fn (Kill $k) => $weapon !== null && $mine($k) && $k->weapon === $weapon,
            AwardKey::FirstBlood => fn (Kill $k) => $k->victimId === $steamId && $k->opening,
        };

        return $this->sorted(array_filter($this->killsIn($matchIds, $map), $keep), fn (Kill $k) => [$k->matchId, $k->tick]);
    }

    /** @return list<Kill> */
    private function killsIn(array $matchIds, ?string $map): array
    {
        return array_values(array_filter($this->kills, fn (Kill $k) => in_array($k->matchId, $matchIds, true) && ($map === null || $k->map === $map)));
    }

    /** @return list<Kill> */
    private function sorted(array $kills, callable $key): array
    {
        $kills = array_values($kills);
        usort($kills, fn ($a, $b) => $key($a) <=> $key($b));

        return $kills;
    }
}
