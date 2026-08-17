<?php

namespace Tests\Unit;

use App\Actions\Analysis\BuildRoundCardAction;
use PHPUnit\Framework\TestCase;

// The card IS the retrieval surface: a round can only be found by a phrase that appears
// in its text, so these pin the vocabulary rather than the formatting. Pure string
// building — no DB, no embedder.
class BuildRoundCardActionTest extends TestCase
{
    private function round(array $overrides = []): object
    {
        return (object) array_merge([
            'match_id' => 7, 'round' => 12, 'map' => 'de_mirage', 'winner' => 'CT',
            'reason' => 'elimination', 'ct_alive' => 3, 't_alive' => 0,
            'ct_buy' => 'full', 't_buy' => 'full',
        ], $overrides);
    }

    public function test_names_the_round_map_and_outcome(): void
    {
        $card = (new BuildRoundCardAction)->execute($this->round());

        $this->assertStringContainsString('Round 12', $card);
        $this->assertStringContainsString('de_mirage', $card);
        $this->assertStringContainsString('CT won', $card);
    }

    public function test_enum_reasons_become_words_an_embedder_can_match(): void
    {
        $action = new BuildRoundCardAction;

        // "bomb_defused" is invisible to a model trained on prose; "defusing the bomb" isn't.
        $card = $action->execute($this->round(['reason' => 'bomb_defused']));
        $this->assertStringContainsString('defusing the bomb', $card);
        $this->assertStringNotContainsString('bomb_defused', $card);
    }

    public function test_a_one_alive_win_is_described_as_a_clutch(): void
    {
        $card = (new BuildRoundCardAction)->execute($this->round(['ct_alive' => 1, 't_alive' => 0]));

        $this->assertStringContainsString('clutch', $card);
    }

    public function test_a_save_beating_a_full_buy_is_described_as_an_upset(): void
    {
        $card = (new BuildRoundCardAction)->execute($this->round([
            'winner' => 'T', 'ct_buy' => 'full', 't_buy' => 'eco', 'ct_alive' => 0, 't_alive' => 2,
        ]));

        $this->assertStringContainsString('upset', $card);
        $this->assertStringContainsString('eco', $card);
    }

    public function test_an_ordinary_round_gets_no_invented_drama(): void
    {
        // 3v0 on equal full buys is just a round won — labelling everything a clutch or an
        // upset would make those words useless for retrieval.
        $card = (new BuildRoundCardAction)->execute($this->round());

        $this->assertStringNotContainsString('clutch', $card);
        $this->assertStringNotContainsString('upset', $card);
    }

    public function test_a_dominant_round_is_named_for_questions_about_stomps(): void
    {
        $card = (new BuildRoundCardAction)->execute($this->round(['ct_alive' => 5, 't_alive' => 0]));

        $this->assertStringContainsString('dominant', $card);
    }
}
