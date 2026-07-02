<?php

namespace App\Http\Controllers;

use App\Actions\AuthenticateUserAction;
use App\Actions\RegisterUserAction;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $user = $action->execute($request->validated());

        return $this->tokenResponse($user, 201);
    }

    public function login(LoginRequest $request, AuthenticateUserAction $action): JsonResponse
    {
        $user = $action->execute($request->validated('email'), $request->validated('password'));

        return $this->tokenResponse($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function tokenResponse($user, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $user->createToken('spa')->plainTextToken,
            ],
        ], $status);
    }
}
