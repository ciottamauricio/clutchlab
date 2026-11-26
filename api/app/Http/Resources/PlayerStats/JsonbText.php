<?php

namespace App\Http\Resources\PlayerStats;

// The profile's clutch kills have always carried hitgroups as Postgres prints a jsonb object, a
// string ('{"head": 91, "chest": 20}'), where every other answer gives the object itself. This
// writes that text back from the decoded object, in jsonb's key order, which decoding kept.
// Held to Postgres's own ::text by tests/Postgres/PlayerStats/JsonbTextTest.
final class JsonbText
{
    /** @param  array<string, int>|null  $object */
    public static function of(?array $object): ?string
    {
        if ($object === null) {
            return null;
        }

        $pairs = array_map(fn ($key, $value) => json_encode((string) $key, JSON_UNESCAPED_UNICODE).': '.json_encode($value), array_keys($object), $object);

        return '{'.implode(', ', $pairs).'}';
    }
}
