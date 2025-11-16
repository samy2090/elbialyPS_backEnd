<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOrStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = $request->user();
        
        if (!$user->hasRole('admin') && !$user->hasRole('staff')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. Admin or Staff privileges required.'
            ], 403);
        }

        return $next($request);
    }
}