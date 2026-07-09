<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// A single ability in the catalog (e.g. match.delete). `scope` is 'app' or 'team' — see
// App\Authorization\PermissionCatalog. Grants live in the global_role_permissions and
// team_role_permissions pivots, read by App\Services\PermissionService.
class Permission extends Model
{
    protected $fillable = ['key', 'scope', 'label', 'description'];
}
