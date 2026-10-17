<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated admin access.',
                'error_code' => 'UNAUTHORIZED',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your admin account has been suspended or is inactive.',
                'error_code' => 'ACCOUNT_SUSPENDED',
            ], Response::HTTP_FORBIDDEN);
        }

        foreach ($permissions as $permission) {
            if (! $user->hasPermission($permission)) {
                return response()->json([
                    'success' => false,
                    'message' => "Permission denied. Required permission: {$permission}",
                    'error_code' => 'FORBIDDEN',
                    'required_permission' => $permission,
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
