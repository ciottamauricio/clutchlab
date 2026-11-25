<?php

namespace App\PlatformSync\Adapters\Eloquent;

use App\Models\FaceitAccount;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformAccount;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\SteamId;
use App\PlatformSync\Ports\PlatformAccounts;
use Illuminate\Database\Eloquent\Builder;

// The accounts in their tables: FACEIT's in `faceit_accounts`, one per user. Another platform gets
// its own table, and the match below stops compiling until it has one.
class EloquentPlatformAccounts implements PlatformAccounts
{
    public function of(int $userId, Platform $platform): ?PlatformAccount
    {
        $row = $this->table($platform)->where('user_id', $userId)->first();

        return $row === null ? null : new PlatformAccount(
            (int) $row->user_id,
            $platform,
            new SteamId($row->steam_id),
            new PlatformPlayer($row->player_id, $row->nickname),
        );
    }

    public function save(PlatformAccount $account): void
    {
        $this->table($account->platform)->updateOrCreate(
            ['user_id' => $account->userId],
            ['steam_id' => $account->foundFor->value, 'player_id' => $account->player->playerId, 'nickname' => $account->player->nickname],
        );
    }

    public function remove(int $userId, Platform $platform): void
    {
        $this->table($platform)->where('user_id', $userId)->delete();
    }

    private function table(Platform $platform): Builder
    {
        return match ($platform) {
            Platform::Faceit => FaceitAccount::query(),
        };
    }
}
