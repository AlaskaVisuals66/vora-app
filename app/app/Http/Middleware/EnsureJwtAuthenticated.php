<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureJwtAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (! $user || ! $user->is_active) {
                return response()->json(['message' => 'Usuário inativo ou não encontrado'], 401);
            }
        } catch (TokenExpiredException) {
            return response()->json(['message' => 'Token expirado', 'code' => 'token_expired'], 401);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        return $next($request);
    }
}
