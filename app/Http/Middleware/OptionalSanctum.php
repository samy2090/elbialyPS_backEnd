<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class OptionalSanctum
{
    /**
     * Attempt to authenticate via Bearer token without requiring it.
     * If a valid token is present, the user will be set. Otherwise, the request continues unauthenticated.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken());
            if ($accessToken) {
                $request->setUserResolver(fn () => $accessToken->tokenable);
            }
        }

        return $next($request);
    }
}
