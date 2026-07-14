<?php

namespace Database\Seeders;

use App\Authorization\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Writes the ability catalog and the default grants. The catalog is upserted every run (keys
// stay in sync with the code). Grants are only seeded the first time a permission is created,
// so re-seeding never clobbers an admin's live edits.
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::abilities() as $key => [$scope, $area, $label, $description]) {
            $existed = DB::table('permissions')->where('key', $key)->exists();

            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['scope' => $scope, 'label' => $label, 'description' => $description, 'updated_at' => now(), 'created_at' => now()],
            );

            if (! $existed) {
                $this->seedDefaultsFor($key, $scope);
            }
        }
    }

    private function seedDefaultsFor(string $key, string $scope): void
    {
        $permissionId = DB::table('permissions')->where('key', $key)->value('id');

        if ($scope === PermissionCatalog::TEAM) {
            foreach (PermissionCatalog::teamDefaults() as $role => $keys) {
                if (in_array($key, $keys, true)) {
                    DB::table('team_role_permissions')->insertOrIgnore([
                        'role' => $role, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            return;
        }

        foreach (PermissionCatalog::appDefaults() as $role => $keys) {
            if (in_array($key, $keys, true)) {
                DB::table('global_role_permissions')->insertOrIgnore([
                    'role' => $role, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
