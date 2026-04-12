<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'غير مصرح لك بالوصول إلى هذا المورد'
            ], 403);
        }

        return $next($request);
    }
}
