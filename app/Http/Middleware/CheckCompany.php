<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCompany
{

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'You must be assigned to a company to perform this action.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
