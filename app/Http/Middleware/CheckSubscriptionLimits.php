<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Company;
use Closure;
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
            throw ApiException::forbidden('You must be assigned to a company to use this feature.');
        }

        $message = match ($feature) {
            'members' => $this->membersLimitMessage($company),
            'projects' => $this->projectsLimitMessage($company),
            'ai_chat' => $company->hasAiChatAccess()
                ? null
                : 'Upgrade your plan to use AI chat.',
            default => 'Unknown subscription feature.',
        };

        if ($message !== null) {
            throw ApiException::forbidden($message);
        }

        return $next($request);
    }

    private function membersLimitMessage(Company $company): ?string
    {
        $count = $company->users()->count();

        return $company->canAddMoreMembers($count)
            ? null
            : 'Upgrade your plan to add more members.';
    }

    private function projectsLimitMessage(Company $company): ?string
    {
        $count = $company->projects()->count();

        return $company->canAddMoreProjects($count)
            ? null
            : 'Upgrade your plan to add more projects.';
    }
}
