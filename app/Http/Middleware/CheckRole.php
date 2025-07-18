<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        /** @var \App\Models\User $user */

        if (! $user->hasRole($role)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول',
                'required_role' => $role,
                'your_roles' => $user->getRoleNames()
            ], 403);
        }

        return $next($request);
    }
}
