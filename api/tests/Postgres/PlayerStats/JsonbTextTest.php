<?php

namespace Tests\Postgres\PlayerStats;

use App\Http\Resources\PlayerStats\JsonbText;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Postgres\PostgresTestCase;

// The profile prints a clutch kill's hitgroups the way Postgres prints a jsonb object; JsonbText
// writes it back from the decoded object. Postgres itself is the reference.
class JsonbTextTest extends PostgresTestCase
{
    public static function objects(): array
    {
        return [
            'one zone' => [['head' => 91]],
            'two zones' => [['chest' => 20, 'head' => 91]],
            'every zone' => [['arms' => 12, 'legs' => 30, 'chest' => 40, 'stomach' => 5, 'head' => 100]],
            'empty' => [[]],
        ];
    }

    /** @param  array<string, int>  $object */
    #[DataProvider('objects')]
    public function test_the_text_is_what_postgres_prints(array $object): void
    {
        $text = DB::selectOne('select (?::jsonb)::text as t', [json_encode((object) $object)])->t;

        $this->assertSame($text, JsonbText::of(json_decode($text, true)));
    }

    public function test_no_hitgroups_stay_null(): void
    {
        $this->assertNull(JsonbText::of(null));
    }
}
