<?php

namespace Tests\Feature\PlatformSync;

use App\Models\User;
use App\PlatformSync\Adapters\Eloquent\EloquentPlatformAccounts;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformAccount;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\SteamId;
use App\PlatformSync\Ports\PlatformAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\PlatformSync\InMemoryPlatformAccounts;
use Tests\TestCase;

// The PlatformAccounts contract, run on the in-memory adapter the use-case tests trust and on the
// real one, so the two can't drift apart.
class PlatformAccountsContractTest extends TestCase
{
    use RefreshDatabase;

    public static function adapters(): array
    {
        return ['in memory' => ['memory'], 'eloquent' => ['eloquent']];
    }

    private function adapter(string $kind): PlatformAccounts
    {
        return $kind === 'eloquent' ? $this->app->make(EloquentPlatformAccounts::class) : new InMemoryPlatformAccounts;
    }

    /** @return array{0: int, 1: int} two users' ids */
    private function users(string $kind): array
    {
        return $kind === 'eloquent' ? [User::factory()->create()->id, User::factory()->create()->id] : [7, 8];
    }

    private function account(int $userId, string $steamId = '76561198963438273', string $playerId = 'p-1', string $nickname = 'FiveAlert'): PlatformAccount
    {
        return new PlatformAccount($userId, Platform::Faceit, new SteamId($steamId), new PlatformPlayer($playerId, $nickname));
    }

    #[DataProvider('adapters')]
    public function test_a_user_without_an_account_has_none(string $kind): void
    {
        [$user] = $this->users($kind);

        $this->assertNull($this->adapter($kind)->of($user, Platform::Faceit));
    }

    #[DataProvider('adapters')]
    public function test_a_saved_account_reads_back_as_it_was(string $kind): void
    {
        $accounts = $this->adapter($kind);
        [$user] = $this->users($kind);

        $accounts->save($this->account($user));

        $this->assertEquals($this->account($user), $accounts->of($user, Platform::Faceit));
    }

    #[DataProvider('adapters')]
    public function test_saving_again_replaces_the_users_account(string $kind): void
    {
        $accounts = $this->adapter($kind);
        [$user] = $this->users($kind);

        $accounts->save($this->account($user));
        $accounts->save($this->account($user, '76561190000000001', 'p-2', 'Other'));

        $this->assertEquals($this->account($user, '76561190000000001', 'p-2', 'Other'), $accounts->of($user, Platform::Faceit));
        if ($kind === 'eloquent') {
            $this->assertDatabaseCount('faceit_accounts', 1);
        }
    }

    #[DataProvider('adapters')]
    public function test_removing_forgets_only_that_users_account(string $kind): void
    {
        $accounts = $this->adapter($kind);
        [$user, $other] = $this->users($kind);
        $accounts->save($this->account($user));
        $accounts->save($this->account($other, '76561190000000001', 'p-2', 'Other'));

        $accounts->remove($user, Platform::Faceit);

        $this->assertNull($accounts->of($user, Platform::Faceit));
        $this->assertSame('Other', $accounts->of($other, Platform::Faceit)->player->nickname);
    }
}
