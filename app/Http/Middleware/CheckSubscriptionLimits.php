<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimits
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $company = $request->user()?->company;

        if ($company === null) {
            return $this->forbidden('You must be assigned to a company to use this feature.');
        }

        $message = match ($feature) {
            'members' => $company->canAddMoreMembers($company->users()->count())
                ? null
                : 'Upgrade your plan to add more members.',
            'projects' => $company->canAddMoreProjects($company->projects()->count())
                ? null
                : 'Upgrade your plan to add more projects.',
            'ai_chat' => $company->hasAiChatAccess()
                ? null
                : 'Upgrade your plan to use AI chat.',
            default => 'Unknown subscription feature.',
        };

        if ($message !== null) {
            return $this->forbidden($message);
        }

        return $next($request);
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'SUBSCRIPTION_LIMIT_REACHED',
            'data' => [],
        ], Response::HTTP_FORBIDDEN);
    }
}
