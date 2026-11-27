<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pembatasan akses berdasarkan role, dicek di SERVER (bukan hanya di menu aplikasi).
 * Pemakaian di rute: ->middleware('role:owner') atau ->middleware('role:admin,owner')
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke data ini.',
            ], 403);
        }

        return $next($request);
    }
}
