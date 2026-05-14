<?php

namespace App\Domain\Auth\Http\Controllers;

use App\Domain\Auth\Http\Requests\LoginRequest;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\AuditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Muitas tentativas. Tente novamente em alguns instantes.',
            ], 429);
        }

        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }
        if (! $user->is_active) {
            return response()->json(['message' => 'Usuário inativo'], 403);
        }

        RateLimiter::clear($key);

        $token = JWTAuth::fromUser($user);
        $this->audit->log($user, 'auth.login', $request);

        return $this->respondWithToken($token, $user);
    }

    public function refresh(): JsonResponse
    {
        $newToken = JWTAuth::parseToken()->refresh();
        return response()->json([
            'access_token' => $newToken,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles','sectors']);
        return response()->json(['user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        JWTAuth::invalidate(JWTAuth::getToken());
        $this->audit->log($user, 'auth.logout', $request);
        return response()->json(['message' => 'Sessão encerrada']);
    }

    private function respondWithToken(string $token, User $user): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
            'user' => $user->only(['id','name','email','tenant_id']) + ['roles' => $user->getRoleNames()],
        ]);
    }
}
