<?php

namespace App\Actions;

use App\Authorization\PermissionCatalog;
use Illuminate\Support\Facades\DB;

// Set a single role's grants (for one scope) to exactly the given ability keys — inserting the
// newly-checked and deleting the newly-unchecked. Idempotent.
class UpdateRolePermissionsAction
{
    /**
     * @param  list<string>  $keys  the role's complete desired ability list
     */
    public function execute(string $scope, string $role, array $keys): void
    {
        $table = $scope === PermissionCatalog::APP ? 'global_role_permissions' : 'team_role_permissions';

        $ids = DB::table('permissions')
            ->where('scope', $scope)
            ->whereIn('key', $keys)
            ->pluck('id')
            ->all();

        $inScopeIds = DB::table('permissions')->where('scope', $scope)->pluck('id')->all();

        DB::transaction(function () use ($table, $role, $ids, $inScopeIds) {
            // Remove any grants for this role that aren't in the desired set (bounded to this scope).
            DB::table($table)
                ->where('role', $role)
                ->whereIn('permission_id', $inScopeIds)
                ->whereNotIn('permission_id', $ids ?: [0])
                ->delete();

            foreach ($ids as $id) {
                DB::table($table)->insertOrIgnore([
                    'role' => $role,
                    'permission_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
