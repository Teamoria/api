<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->company_id === null) {
            throw ApiException::forbidden('You must be assigned to a company to perform this action.');
        }

        return $next($request);
    }
}
