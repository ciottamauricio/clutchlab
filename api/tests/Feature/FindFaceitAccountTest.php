<?php

namespace Tests\Feature;

use App\Actions\Faceit\FaceitSyncOff;
use App\Actions\Faceit\FindFaceitAccountAction;
use App\Contracts\FaceitUnavailable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Spec 001 AC-7 to AC-10: the FACEIT account comes from the profile's Steam ID, is remembered, and
// is looked up again when that Steam ID changes.
class FindFaceitAccountTest extends TestCase
{
    use RefreshDatabase;

    private const STEAM = '76561198963438273';

    private function userWithSteamId(?string $steamId): User
    {
        $user = User::factory()->create();
        $user->forceFill(['steam_id' => $steamId])->save();

        return $user;
    }

    private function find(User $user)
    {
        return app(FindFaceitAccountAction::class)->execute($user->fresh());
    }

    public function test_the_account_is_found_from_the_steam_id_and_stored(): void
    {
        $this->faceit->players[self::STEAM] = ['player_id' => 'p-1', 'nickname' => 'FiveAlert'];
        $user = $this->userWithSteamId(self::STEAM);

        $lookup = $this->find($user);

        $this->assertNull($lookup->reason);
        $this->assertSame('FiveAlert', $lookup->account->nickname);
        $this->assertDatabaseHas('faceit_accounts', ['user_id' => $user->id, 'steam_id' => self::STEAM, 'player_id' => 'p-1']);
    }

    public function test_faceit_is_asked_once_while_the_steam_id_is_unchanged(): void
    {
        $this->faceit->players[self::STEAM] = ['player_id' => 'p-1', 'nickname' => 'FiveAlert'];
        $user = $this->userWithSteamId(self::STEAM);

        $this->find($user);
        $this->find($user);

        $this->assertSame(1, $this->faceit->callsTo('findPlayerBySteamId'));
    }

    public function test_a_changed_steam_id_is_looked_up_again(): void
    {
        $this->faceit->players[self::STEAM] = ['player_id' => 'p-1', 'nickname' => 'FiveAlert'];
        $this->faceit->players['76561190000000001'] = ['player_id' => 'p-2', 'nickname' => 'Other'];
        $user = $this->userWithSteamId(self::STEAM);
        $this->find($user);

        $user->forceFill(['steam_id' => '76561190000000001'])->save();
        $lookup = $this->find($user);

        $this->assertSame('Other', $lookup->account->nickname);
        $this->assertSame(2, $this->faceit->callsTo('findPlayerBySteamId'));
        $this->assertDatabaseCount('faceit_accounts', 1);
    }

    public function test_no_steam_id_means_no_lookup(): void
    {
        $lookup = $this->find($this->userWithSteamId(null));

        $this->assertSame('faceit.no_steam_id', $lookup->reason);
        $this->assertNull($lookup->account);
        $this->assertSame([], $this->faceit->calls);
    }

    public function test_a_steam_id_with_no_account_is_not_asked_again_for_five_minutes(): void
    {
        $user = $this->userWithSteamId(self::STEAM);

        $this->assertSame('faceit.not_found', $this->find($user)->reason);
        $this->travel(4)->minutes();
        $this->assertSame('faceit.not_found', $this->find($user)->reason);
        $this->assertSame(1, $this->faceit->callsTo('findPlayerBySteamId'));

        $this->travel(2)->minutes();
        $this->find($user);
        $this->assertSame(2, $this->faceit->callsTo('findPlayerBySteamId'));
    }

    public function test_a_removed_steam_id_forgets_the_account(): void
    {
        $this->faceit->players[self::STEAM] = ['player_id' => 'p-1', 'nickname' => 'FiveAlert'];
        $user = $this->userWithSteamId(self::STEAM);
        $this->find($user);

        $user->forceFill(['steam_id' => null])->save();

        $this->assertSame('faceit.no_steam_id', $this->find($user)->reason);
        $this->assertDatabaseCount('faceit_accounts', 0);
    }

    public function test_faceit_being_down_is_not_a_missing_account(): void
    {
        $this->faceit->unavailable = true;

        $this->expectException(FaceitUnavailable::class);
        $this->find($this->userWithSteamId(self::STEAM));
    }

    public function test_without_a_key_sync_is_off_before_any_other_reason(): void
    {
        $this->faceit->configured = false;

        try {
            $this->find($this->userWithSteamId(null));
            $this->fail('expected FaceitSyncOff');
        } catch (FaceitSyncOff) {
            $this->assertSame([], $this->faceit->calls);
        }
    }
}
