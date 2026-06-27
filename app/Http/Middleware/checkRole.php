<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class checkRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {

        $user = Auth::user();
        if (! in_array($user->role->value, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this resource.',
                'data' => null,
            ], 403);
        }

        return $next($request);

    }
}
