<?php

namespace App\Models\Concerns;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;

trait HasSubscriptionLimits
{
    public function activeSubscription(): ?Subscription
    {
        $now = now();
        $gracePeriodStartsAt = $now->copy()->subDays(3);

        return $this->subscriptions()
            ->with('plan')
            ->where(function (Builder $query) use ($now, $gracePeriodStartsAt): void {
                $query
                    ->where(function (Builder $query) use ($now): void {
                        $query
                            ->where('status', SubscriptionStatus::TRIALING->value)
                            ->where(function (Builder $query) use ($now): void {
                                $query
                                    ->whereNull('trial_ends_at')
                                    ->orWhere('trial_ends_at', '>=', $now);
                            });
                    })
                    ->orWhere(function (Builder $query) use ($now): void {
                        $query
                            ->where('status', SubscriptionStatus::ACTIVE->value)
                            ->where(function (Builder $query) use ($now): void {
                                $query
                                    ->whereNull('ends_at')
                                    ->orWhere('ends_at', '>=', $now);
                            });
                    })
                    ->orWhere(function (Builder $query) use ($now, $gracePeriodStartsAt): void {
                        $query
                            ->where('status', SubscriptionStatus::PAST_DUE->value)
                            ->whereBetween('ends_at', [$gracePeriodStartsAt, $now]);
                    });
            })
            ->latest('starts_at')
            ->latest()
            ->first();
    }

    public function canAddMoreMembers(int $currentMembersCount): bool
    {
        $plan = $this->activeSubscription()?->plan;

        return $plan !== null && $currentMembersCount < $plan->max_members;
    }

    public function canAddMoreProjects(int $currentProjectsCount): bool
    {
        $plan = $this->activeSubscription()?->plan;

        return $plan !== null && $currentProjectsCount < $plan->max_projects;
    }

    public function hasAiChatAccess(): bool
    {
        $plan = $this->activeSubscription()?->plan;

        return $plan !== null && $plan->has_ai_features;
    }
}
