<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCompany
{

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::user()->company_id) {
            throw ApiException::forbidden('You must be assigned to a company to perform this action.');

        }

        return $next($request);
    }
}
