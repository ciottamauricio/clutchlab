<?php

namespace App\Http\Controllers;

use App\Actions\UpdateUserAction;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        return AdminUserResource::collection(
            User::with('teams')->orderBy('name')->get()
        )->response();
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): AdminUserResource
    {
        $action->execute($user, $request->validated());

        return new AdminUserResource($user->load('teams'));
    }
}
