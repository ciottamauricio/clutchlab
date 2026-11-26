<?php

namespace Tests\Fixtures\PlayerStats;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Spec 004's fixture: the cases the dev data showed (AC-2), defined once as plain data so the same
// matches can be written to a database or loaded into the in-memory adapters. Small enough that
// every expected number in the tests can be worked out by hand from the lists below.
//
// The team "Alpha" has four matches (m1–m4) and a roster of p1–p4; m5 is another team's, m6 the
// owner's private match. Sides swap between matches: p1/p2 are CT in m1 and m3, T in m2.
final class Scenario
{
    public const P1 = '76561190000000001';   // the owner's own Steam ID

    public const P2 = '76561190000000002';   // renamed: "Two", then "Two v2" in the last upload

    public const P3 = '76561190000000003';   // rostered with a nickname, never played

    public const P4 = '76561190000000004';   // rostered, no nickname, never played

    public const E1 = '76561190000000011';   // 19 kills, all pistol or knife: one under the floor

    public const E2 = '76561190000000012';   // 22 kills, split evenly: a tied top weapon

    public const X1 = '76561190000000021';   // plays only in the other team's match

    public const NO_KILLER = '';              // the bomb: kills with no killer

    public const NAMES = [
        self::P1 => 'One', self::E1 => 'Eco One', self::E2 => 'Tied', self::X1 => 'Xavier',
    ];

    /** @var array<string, array<string, mixed>> the matches, by key */
    public array $matches = [];

    /** @var list<array<string, mixed>> kill_events rows, with 'match' as a match key */
    public array $kills = [];

    /** @var array<string, array<string, array{name: string, side: ?string, assists: int}>> who played each match */
    public array $lineups = [];

    /** @var array<string, int> match key → id, once written */
    public array $ids = [];

    public User $owner;

    public User $outsider;

    public User $admin;

    public Team $team;

    public Team $emptyTeam;

    public Team $otherTeam;

    public function __construct()
    {
        $this->match('m1', team: 'team', map: 'de_mirage', played: '2026-11-05 20:00:00', uploaded: '2026-11-06 10:00:00', tick: 64, ct: 13, t: 9, ctName: 'Alpha', duration: 2000.5);
        $this->match('m2', team: 'team', map: 'de_inferno', played: '2026-10-20 18:00:00', uploaded: '2026-11-06 10:00:00', tick: 64, ct: 13, t: 13, duration: 1800.0);
        $this->match('m3', team: 'team', map: 'de_mirage', played: null, uploaded: '2026-11-07 09:00:00', tick: 128, ct: 5, t: 13);
        $this->match('m4', team: 'team', map: null, played: null, uploaded: '2026-11-08 09:00:00', status: 'queued');
        $this->match('m5', team: 'other', map: 'de_mirage', played: '2026-11-10 21:00:00', uploaded: '2026-11-10 22:00:00', tick: 64, ct: 13, t: 2);
        $this->match('m6', team: null, map: 'de_nuke', played: '2026-11-09 12:00:00', uploaded: '2026-11-09 13:00:00', tick: 64, ct: 16, t: 4);

        $this->lineup('m1', ct: [self::P1, self::P2], t: [self::E1, self::E2]);
        $this->lineup('m2', ct: [self::E1, self::E2], t: [self::P1, self::P2]);
        $this->lineup('m3', ct: [self::P1, self::P2], t: [self::E1, self::E2], p2: 'Two v2');
        $this->lineup('m5', ct: [self::P1], t: [self::X1]);
        $this->lineup('m6', ct: [self::P1], t: [self::E1]);

        // m1: p1 opens rounds 1–5, ak47 for 15 rounds then awp, a headshot every even round. The
        // first five carry hitgroups: two leg-dominant (a clear win, a tie with the head), three not.
        // Hitgroups are written in the order jsonb keeps them (shorter keys first, then by name),
        // so values read back from Postgres equal the ones defined here.
        $hitgroups = [1 => ['legs' => 50, 'chest' => 40], 2 => ['head' => 30, 'legs' => 30], 3 => ['head' => 90, 'legs' => 10], 4 => ['legs' => 0, 'chest' => 100], 5 => ['chest' => 100]];
        for ($r = 1; $r <= 20; $r++) {
            $this->kill('m1', $r, self::P1, self::E2, $r <= 15 ? 'ak47' : 'awp', side: 'CT', headshot: $r % 2 === 0, opening: $r <= 5, hitgroups: $hitgroups[$r] ?? null);
        }
        // Round 21: e1 opens, then p1 wins a 1v2.
        $this->kill('m1', 21, self::E1, self::P2, 'deserteagle', side: 'T', opening: true, tick: 21000);
        $this->kill('m1', 21, self::P1, self::E1, 'ak47', side: 'CT', clutch: 2, tick: 21100, hitgroups: ['head' => 91, 'chest' => 20]);
        $this->kill('m1', 21, self::P1, self::E2, 'ak47', side: 'CT', clutch: 2, tick: 21200);
        $this->kill('m1', 22, self::P2, self::E1, 'm4a1', side: 'CT', opening: true);
        // Round 23 opens with a team kill, round 24 with a self-kill by the world.
        $this->kill('m1', 23, self::P1, self::P2, 'ak47', side: 'CT', opening: true);
        $this->kill('m1', 24, self::P2, self::P2, 'world', side: 'CT', opening: true);

        // m2 (de_inferno): e1 opens rounds 1–10 on p2, 16 deserteagle kills and a knife; e2 kills p1
        // 22 times, awp and m4a1 alternating; p2 wins a 1v3 in round 20; the bomb kills p1 20 times.
        for ($r = 1; $r <= 17; $r++) {
            $this->kill('m2', $r, self::E1, self::P2, $r === 17 ? 'knife' : 'deserteagle', side: 'CT', headshot: $r <= 3, opening: $r <= 10);
        }
        for ($r = 1; $r <= 22; $r++) {
            $this->kill('m2', $r, self::E2, self::P1, $r % 2 === 1 ? 'awp' : 'm4a1', side: 'CT', tick: $r * 1000 + 500);
        }
        $this->kill('m2', 20, self::P2, self::E1, 'ak47', side: 'T', clutch: 3, tick: 20100);
        $this->kill('m2', 20, self::P2, self::E2, 'ak47', side: 'T', clutch: 3, tick: 20200);
        for ($r = 23; $r <= 42; $r++) {
            $this->kill('m2', $r, self::NO_KILLER, self::P1, 'c4', side: null);
        }

        // m3 (undated): round 1 opens with three kills on the same tick, so all three are openings.
        $this->kill('m3', 1, self::P1, self::E1, 'ak47', side: 'CT', opening: true, tick: 1000);
        $this->kill('m3', 1, self::P2, self::E2, 'm4a1', side: 'CT', opening: true, tick: 1000);
        $this->kill('m3', 1, self::E1, self::P1, 'deserteagle', side: 'T', opening: true, tick: 1000);
        $this->kill('m3', 2, self::P2, self::E2, 'usps', side: 'CT', headshot: true, tick: 2000);

        // m5: another team's match, never visible to the owner.
        for ($r = 1; $r <= 5; $r++) {
            $this->kill('m5', $r, self::P1, self::X1, 'ak47', side: 'CT', opening: true);
        }

        // m6: the owner's private match, visible to them but not on the team's board.
        $this->kill('m6', 1, self::P1, self::E1, 'awp', side: 'CT', opening: true);
        $this->kill('m6', 2, self::P1, self::E1, 'awp', side: 'CT');
    }

    // Users, teams, rosters, the matches and their analytics rows, written through the database.
    public function intoDatabase(): self
    {
        $this->owner = User::factory()->create(['steam_id' => self::P1]);
        $this->outsider = User::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->team = Team::create(['name' => 'Alpha']);
        $this->team->members()->attach($this->owner, ['role' => 'owner']);
        $this->team->players()->create(['steam_id' => self::P1]);
        $this->team->players()->create(['steam_id' => self::P2]);
        $this->team->players()->create(['steam_id' => self::P3, 'nickname' => 'Three']);
        $this->team->players()->create(['steam_id' => self::P4]);

        $this->emptyTeam = Team::create(['name' => 'Empty']);
        $this->emptyTeam->members()->attach($this->owner, ['role' => 'owner']);

        $this->otherTeam = Team::create(['name' => 'Other']);
        $this->otherTeam->members()->attach($this->outsider, ['role' => 'owner']);

        $teams = ['team' => $this->team->id, 'other' => $this->otherTeam->id, null => null];
        foreach ($this->matches as $key => $m) {
            $this->ids[$key] = DB::table('matches')->insertGetId([
                'user_id' => $m['team'] === 'other' ? $this->outsider->id : $this->owner->id,
                'team_id' => $teams[$m['team']],
                'original_filename' => "{$key}.dem",
                'demo_key' => "{$key}-key.dem",
                'status' => $m['status'],
                'map_name' => $m['map'],
                'score_ct' => $m['ct'],
                'score_t' => $m['t'],
                'ct_name' => $m['ctName'],
                'tick_rate' => $m['tick'],
                'duration_seconds' => $m['duration'],
                'played_at' => $m['played'],
                'created_at' => $m['uploaded'],
                'updated_at' => $m['uploaded'],
                'platform' => 'gamersclub',
            ]);
        }

        foreach ($this->scoreboard() as $row) {
            DB::table('match_player_stats')->insert(['match_id' => $this->ids[$row['match']]] + array_diff_key($row, ['match' => 0]));
        }

        foreach ($this->kills as $k) {
            DB::table('kill_events')->insert(['match_id' => $this->ids[$k['match']]] + array_diff_key($k, ['match' => 0]) + [
                'owner_id' => $this->matches[$k['match']]['team'] === 'other' ? $this->outsider->id : $this->owner->id,
            ]);
        }

        return $this;
    }

    public function id(string $match): int
    {
        return $this->ids[$match];
    }

    // One row per player per match, tallied from the kills as the worker does: the scoreboard and
    // kill_events agree on the dev data, so they agree here.
    /** @return list<array<string, mixed>> */
    public function scoreboard(): array
    {
        $rows = [];
        foreach ($this->lineups as $match => $players) {
            foreach ($players as $steamId => $p) {
                $steamId = (string) $steamId; // numeric-string keys come back as ints
                $mine = fn (string $role) => array_filter($this->kills, fn ($k) => $k['match'] === $match && $k[$role] === $steamId);
                $rows[] = [
                    'match' => $match,
                    'steam_id' => $steamId,
                    'name' => $p['name'],
                    'team_side' => $p['side'],
                    'kills' => count($mine('killer_steam_id')),
                    'deaths' => count($mine('victim_steam_id')),
                    'assists' => $p['assists'],
                    'headshots' => count(array_filter($mine('killer_steam_id'), fn ($k) => $k['headshot'])),
                ];
            }
        }

        return $rows;
    }

    private function match(string $key, ?string $team, ?string $map, ?string $played, string $uploaded, ?float $tick = null, ?int $ct = null, ?int $t = null, ?string $ctName = null, ?float $duration = null, string $status = 'parsed'): void
    {
        $this->matches[$key] = compact('team', 'map', 'played', 'uploaded', 'tick', 'ct', 't', 'ctName', 'duration', 'status');
    }

    /** @param list<string> $ct @param list<string> $t */
    private function lineup(string $match, array $ct, array $t, ?string $p2 = null): void
    {
        foreach (['CT' => $ct, 'T' => $t] as $side => $ids) {
            foreach ($ids as $id) {
                $name = $id === self::P2 ? ($p2 ?? 'Two') : self::NAMES[$id];
                $this->lineups[$match][$id] = ['name' => $name, 'side' => $side, 'assists' => 0];
            }
        }
    }

    private function kill(string $match, int $round, string $killer, string $victim, string $weapon, ?string $side, bool $headshot = false, bool $opening = false, int $clutch = 0, ?int $tick = null, ?array $hitgroups = null): void
    {
        $name = fn (string $id) => $id === self::NO_KILLER ? null : ($this->lineups[$match][$id]['name'] ?? self::NAMES[$id]);
        $this->kills[] = [
            'match' => $match,
            'map' => $this->matches[$match]['map'],
            'round' => $round,
            'killer_steam_id' => $killer,
            'killer_name' => $name($killer),
            'victim_steam_id' => $victim,
            'victim_name' => $name($victim),
            'weapon' => $weapon,
            'headshot' => $headshot,
            'opening' => $opening,
            'side' => $side,
            'killer_team' => $side,
            'clutch' => $clutch,
            'tick' => $tick ?? $round * 1000,
            'hitgroups' => $hitgroups === null ? null : json_encode($hitgroups),
            'victim_x' => 1.5,
            'victim_y' => -2.5,
        ];
    }
}
