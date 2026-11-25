<?php

namespace Tests\Unit\PlatformSync;

use App\PlatformSync\Application\MissingAccount;
use App\PlatformSync\Application\SyncOff;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Ports\PlatformUnavailable;

// Spec 001 AC-7 to AC-10: the account comes from the profile's Steam ID, is remembered, and is
// looked up again when that Steam ID changes. Formerly FindFaceitAccountTest, on the database.
class FindAccountTest extends UseCaseTestCase
{
    private const USER = 7;

    private const STEAM = '76561198963438273';

    private const OTHER_STEAM = '76561190000000001';

    public function test_the_account_is_found_from_the_steam_id_and_stored(): void
    {
        $this->platform->players[self::STEAM] = new PlatformPlayer('p-1', 'FiveAlert');

        $lookup = $this->findAccount()->execute($this->viewer(self::USER, self::STEAM));

        $this->assertNull($lookup->reason);
        $this->assertSame('FiveAlert', $lookup->account->player->nickname);
        $stored = $this->accounts->of(self::USER, Platform::Faceit);
        $this->assertSame(self::STEAM, $stored->foundFor->value);
        $this->assertSame('p-1', $stored->player->playerId);
    }

    public function test_the_platform_is_asked_once_while_the_steam_id_is_unchanged(): void
    {
        $this->platform->players[self::STEAM] = new PlatformPlayer('p-1', 'FiveAlert');

        $this->findAccount()->execute($this->viewer(self::USER, self::STEAM));
        $this->findAccount()->execute($this->viewer(self::USER, self::STEAM));

        $this->assertSame(1, $this->platform->callsTo('findPlayerBySteamId'));
    }

    public function test_a_changed_steam_id_is_looked_up_again(): void
    {
        $this->platform->players[self::STEAM] = new PlatformPlayer('p-1', 'FiveAlert');
        $this->platform->players[self::OTHER_STEAM] = new PlatformPlayer('p-2', 'Other');
        $this->findAccount()->execute($this->viewer(self::USER, self::STEAM));

        $lookup = $this->findAccount()->execute($this->viewer(self::USER, self::OTHER_STEAM));

        $this->assertSame('Other', $lookup->account->player->nickname);
        $this->assertSame(2, $this->platform->callsTo('findPlayerBySteamId'));
        $this->assertCount(1, $this->accounts->accounts);
    }

    public function test_no_steam_id_means_no_lookup(): void
    {
        $lookup = $this->findAccount()->execute($this->viewer(self::USER, null));

        $this->assertSame(MissingAccount::NoSteamId, $lookup->reason);
        $this->assertNull($lookup->account);
        $this->assertSame([], $this->platform->calls);
    }

    public function test_a_steam_id_with_no_account_is_not_asked_again_for_five_minutes(): void
    {
        $viewer = $this->viewer(self::USER, self::STEAM);

        $this->assertSame(MissingAccount::NotFound, $this->findAccount()->execute($viewer)->reason);
        $this->cache->advance(4 * 60);
        $this->assertSame(MissingAccount::NotFound, $this->findAccount()->execute($viewer)->reason);
        $this->assertSame(1, $this->platform->callsTo('findPlayerBySteamId'));

        $this->cache->advance(2 * 60);
        $this->findAccount()->execute($viewer);
        $this->assertSame(2, $this->platform->callsTo('findPlayerBySteamId'));
    }

    public function test_a_removed_steam_id_forgets_the_account(): void
    {
        $this->platform->players[self::STEAM] = new PlatformPlayer('p-1', 'FiveAlert');
        $this->findAccount()->execute($this->viewer(self::USER, self::STEAM));

        $lookup = $this->findAccount()->execute($this->viewer(self::USER, null));

        $this->assertSame(MissingAccount::NoSteamId, $lookup->reason);
        $this->assertNull($this->accounts->of(self::USER, Platform::Faceit));
    }

    public function test_a_new_steam_id_that_is_on_no_account_drops_the_old_one(): void
    {
        $this->platform->players[self::STEAM] = new PlatformPlayer('p-1', 'FiveAlert');
        $this->findAccount()->execute($this->viewer(self::USER, self::STEAM));

        $lookup = $this->findAccount()->execute($this->viewer(self::USER, self::OTHER_STEAM));

        $this->assertSame(MissingAccount::NotFound, $lookup->reason);
        $this->assertNull($this->accounts->of(self::USER, Platform::Faceit));
    }

    public function test_the_platform_being_down_is_not_a_missing_account(): void
    {
        $this->platform->unavailable = true;

        $this->expectException(PlatformUnavailable::class);
        $this->findAccount()->execute($this->viewer(self::USER, self::STEAM));
    }

    public function test_without_a_key_sync_is_off_before_any_other_reason(): void
    {
        $this->enabled = false;

        try {
            $this->findAccount()->execute($this->viewer(self::USER, null));
            $this->fail('expected SyncOff');
        } catch (SyncOff) {
            $this->assertSame([], $this->platform->calls);
        }
    }
}
