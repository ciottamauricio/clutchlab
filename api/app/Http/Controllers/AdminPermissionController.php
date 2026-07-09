<?php

namespace App\Http\Controllers;

use App\Actions\UpdateRolePermissionsAction;
use App\Authorization\PermissionCatalog;
use App\Http\Requests\UpdateRolePermissionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

// The grant matrix an admin edits: the catalog of abilities, the roles per scope, and which
// roles currently hold each ability. Admin-only (route middleware `can:admin`).
class AdminPermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $abilities = [];
        foreach (PermissionCatalog::abilities() as $key => [$scope, $area, $label, $description]) {
            $abilities[] = compact('key', 'scope', 'area', 'label', 'description');
        }

        return response()->json(['data' => [
            'abilities' => $abilities,
            'roles' => [
                'app' => [PermissionCatalog::MEMBER, PermissionCatalog::ADMIN],
                'team' => [PermissionCatalog::OWNER, PermissionCatalog::IGL, PermissionCatalog::PLAYER, PermissionCatalog::COACH],
            ],
            'grants' => [
                'app' => $this->grants('global_role_permissions'),
                'team' => $this->grants('team_role_permissions'),
            ],
        ]]);
    }

    public function update(UpdateRolePermissionsRequest $request, UpdateRolePermissionsAction $action): JsonResponse
    {
        $action->execute(
            $request->validated('scope'),
            $request->validated('role'),
            $request->validated('keys'),
        );

        return response()->json(['data' => ['ok' => true]]);
    }

    // role => list<ability key> for one grant table.
    /** @return array<string, list<string>> */
    private function grants(string $table): array
    {
        return DB::table("{$table} as g")
            ->join('permissions as p', 'p.id', '=', 'g.permission_id')
            ->get(['g.role', 'p.key'])
            ->groupBy('role')
            ->map(fn ($rows) => $rows->pluck('key')->values()->all())
            ->all();
    }
}
