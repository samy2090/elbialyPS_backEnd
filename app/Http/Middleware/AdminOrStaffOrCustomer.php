<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOrStaffOrCustomer
{
    /**
     * Handle an incoming request.
     * Allows Admin, Staff, or Customer roles.
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

        if (!$user->hasRole('admin') && !$user->hasRole('staff') && !$user->hasRole('customer')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. Admin, Staff or Customer privileges required.'
            ], 403);
        }

        return $next($request);
    }
}
