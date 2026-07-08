<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionCanceledNotification;
use App\Notifications\SubscriptionPastDueNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Signature('app:check-subscriptions')]
#[Description('Mark expired subscriptions as past due or canceled.')]
class CheckExpiredSubscriptionsCommand extends Command
{
    public function handle(): int
    {
        $now = now();

        $this->info('Checking expired subscriptions...');

        $pastDueCount = $this->markExpiredSubscriptionsPastDue($now);
        $canceledCount = $this->cancelExpiredPastDueSubscriptions($now);

        $this->info("Subscriptions moved to past due: {$pastDueCount}");
        $this->info("Subscriptions canceled: {$canceledCount}");
        $this->info('Subscription expiration check completed.');

        return self::SUCCESS;
    }

    private function markExpiredSubscriptionsPastDue(Carbon $now): int
    {
        $updated = 0;

        Subscription::query()
            ->with(['company.users', 'plan'])
            ->whereIn('status', [
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::TRIALING->value,
            ])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->chunkById(100, function (Collection $subscriptions) use (&$updated): void {
                $subscriptions->each(function (Subscription $subscription) use (&$updated): void {
                    $subscription->update([
                        'status' => SubscriptionStatus::PAST_DUE,
                    ]);

                    $subscription = $subscription->refresh()->loadMissing(['company.users', 'plan']);

                    $this->notifySubscriptionRecipients(
                        $subscription,
                        new SubscriptionPastDueNotification($subscription),
                    );

                    $updated++;
                });
            });

        return $updated;
    }

    private function cancelExpiredPastDueSubscriptions(Carbon $now): int
    {
        $updated = 0;

        Subscription::query()
            ->with(['company.users', 'plan'])
            ->where('status', SubscriptionStatus::PAST_DUE->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now->copy()->subDays(3))
            ->chunkById(100, function (Collection $subscriptions) use (&$updated): void {
                $subscriptions->each(function (Subscription $subscription) use (&$updated): void {
                    $subscription->update([
                        'status' => SubscriptionStatus::CANCELED,
                    ]);

                    $subscription = $subscription->refresh()->loadMissing(['company.users', 'plan']);

                    $this->notifySubscriptionRecipients(
                        $subscription,
                        new SubscriptionCanceledNotification($subscription),
                    );

                    $updated++;
                });
            });

        return $updated;
    }

    private function notifySubscriptionRecipients(Subscription $subscription, Notification $notification): void
    {
        $recipients = $this->subscriptionRecipients($subscription);

        if ($recipients->isEmpty()) {
            $this->warn("No notification recipients found for subscription {$subscription->id}.");

            return;
        }

        $recipients->each(
            fn (User $user): mixed => $user->notify($notification),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function subscriptionRecipients(Subscription $subscription): Collection
    {
        $companyOwners = $subscription->company?->users
            ->where('role', UserRole::COMPANY_OWNER)
            ->values();

        if ($companyOwners !== null && $companyOwners->isNotEmpty()) {
            return $companyOwners;
        }

        return User::query()
            ->where('role', UserRole::ADMIN->value)
            ->get();
    }
}
