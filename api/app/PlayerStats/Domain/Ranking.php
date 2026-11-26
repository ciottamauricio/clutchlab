<?php

namespace App\PlayerStats\Domain;

final class Ranking
{
    // Highest value first; equal values by Steam ID, ascending (spec 004 Q6). Before, the order
    // among equals was whatever Postgres returned.
    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  callable(T): (int|float)  $value
     * @param  callable(T): string  $steamId
     * @return list<T>
     */
    public static function rank(array $items, callable $value, callable $steamId): array
    {
        usort($items, fn ($a, $b) => [$value($b), $steamId($a)] <=> [$value($a), $steamId($b)]);

        return $items;
    }
}
