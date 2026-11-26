<?php

namespace App\PlayerStats\Domain;

final class AwardRules
{
    // The ratio awards need this many kills, so a player with 1 kill out of 1 can't top them.
    public const MIN_KILLS = 20;

    public const TOP = 5;

    // Every award over the players' counts and weapon kills, in AwardKey order; an award nobody
    // qualifies for is left out. A kill with no killer counts for the empty Steam ID like any
    // player's (spec 004 Q7): over 20 bomb kills, it tops one-trick.
    /**
     * @param  list<PlayerCounts>  $counts
     * @param  list<WeaponKills>  $weaponKills
     * @return list<Award>
     */
    public static function awards(array $counts, array $weaponKills): array
    {
        $awards = [
            self::count(AwardKey::Legshots, $counts, fn (PlayerCounts $c) => $c->legKills),
            self::count(AwardKey::FirstBlood, $counts, fn (PlayerCounts $c) => $c->openingDeaths),
            self::headshot($counts),
            self::count(AwardKey::Eco, $counts, fn (PlayerCounts $c) => $c->ecoKills),
            self::count(AwardKey::Clutches, $counts, fn (PlayerCounts $c) => $c->clutchRounds),
            self::oneTrick($weaponKills),
        ];

        return array_values(array_filter($awards, fn (Award $a) => $a->leaders !== []));
    }

    /**
     * @param  list<PlayerCounts>  $counts
     * @param  callable(PlayerCounts): int  $value
     */
    private static function count(AwardKey $key, array $counts, callable $value): Award
    {
        $leaders = [];
        foreach ($counts as $c) {
            if ($value($c) > 0) {
                $leaders[] = new Leader($c->steamId, $value($c));
            }
        }

        return new Award($key, self::top($leaders));
    }

    /** @param  list<PlayerCounts>  $counts */
    private static function headshot(array $counts): Award
    {
        $leaders = [];
        foreach ($counts as $c) {
            if ($c->kills >= self::MIN_KILLS && $c->headshots > 0) {
                $leaders[] = new Leader($c->steamId, $c->headshots / $c->kills, kills: $c->kills);
            }
        }

        return new Award(AwardKey::Headshot, self::top($leaders));
    }

    // The share of a player's kills made with their top weapon, over the kills with a weapon.
    /** @param  list<WeaponKills>  $weaponKills */
    private static function oneTrick(array $weaponKills): Award
    {
        $byPlayer = [];
        foreach ($weaponKills as $w) {
            $byPlayer[$w->steamId][$w->weapon] = $w->kills;
        }

        $leaders = [];
        foreach ($byPlayer as $steamId => $kills) {
            $total = array_sum($kills);
            if ($total >= self::MIN_KILLS) {
                $top = (string) TopWeapon::of($kills);
                $leaders[] = new Leader((string) $steamId, $kills[$top] / $total, weapon: $top);
            }
        }

        return new Award(AwardKey::OneTrick, self::top($leaders));
    }

    /**
     * @param  list<Leader>  $leaders
     * @return list<Leader>
     */
    private static function top(array $leaders): array
    {
        return array_slice(Ranking::rank($leaders, fn (Leader $l) => $l->value, fn (Leader $l) => $l->steamId), 0, self::TOP);
    }
}
