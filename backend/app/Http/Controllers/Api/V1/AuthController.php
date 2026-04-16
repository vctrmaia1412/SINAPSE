<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ]);

        $plainTextToken = $user->createToken('auth')->plainTextToken;

        return $this->respondWithToken($user, $plainTextToken, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => __('auth.failed'),
                'errors' => [
                    'email' => [__('auth.failed')],
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $plainTextToken = $user->createToken('auth')->plainTextToken;

        return $this->respondWithToken($user, $plainTextToken, Response::HTTP_OK);
    }

    public function logout(Request $request): Response
    {
        $plain = $request->bearerToken();
        if ($plain !== null) {
            PersonalAccessToken::findToken($plain)?->delete();
        } elseif ($token = $request->user()?->currentAccessToken()) {
            $token->delete();
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function respondWithToken(User $user, string $plainTextToken, int $status): JsonResponse
    {
        return (new UserResource($user))
            ->additional([
                'meta' => [
                    'token' => $plainTextToken,
                ],
            ])
            ->response()
            ->setStatusCode($status);
    }
}
