<?php

namespace App\Http\Requests;

use App\Authorization\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Replace the full set of grants for one (scope, role). The client sends the role's complete
// desired ability list; the action diffs it against what's stored.
class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $scope = $this->input('scope');
        $roles = $scope === PermissionCatalog::APP
            ? [PermissionCatalog::MEMBER, PermissionCatalog::ADMIN]
            : [PermissionCatalog::OWNER, PermissionCatalog::IGL, PermissionCatalog::PLAYER, PermissionCatalog::COACH];

        $keysInScope = array_keys(array_filter(
            PermissionCatalog::abilities(),
            fn ($a) => $a[0] === $scope,
        ));

        return [
            'scope' => ['required', 'in:app,team'],
            'role' => ['required', Rule::in($roles)],
            'keys' => ['present', 'array'],
            'keys.*' => [Rule::in($keysInScope)],
        ];
    }

    public function messages(): array
    {
        return [
            'scope.required' => 'permission.invalid_scope',
            'scope.in' => 'permission.invalid_scope',
            'role.in' => 'permission.invalid_role',
            'keys.*.in' => 'permission.unknown_ability',
        ];
    }
}
