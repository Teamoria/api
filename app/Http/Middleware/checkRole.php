<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class checkRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $role = Auth::user()->role->value;
        if (!in_array($role, $roles)) {
            throw new AccessDeniedHttpException('You are not authorized to access this resource.');
        }

        return $next($request);
    }
}
