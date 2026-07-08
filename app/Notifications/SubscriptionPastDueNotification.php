<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPastDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'subscription_past_due';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your current plan';
        $companyName = $this->subscription->company?->name ?? 'your company';

        return (new MailMessage)
            ->subject('Your Teamoria subscription is past due')
            ->greeting("Hello {$notifiable->name},")
            ->line("The {$companyName} subscription for {$planName} is now past due.")
            ->line('Your workspace is in a 3-day grace period. Please submit or confirm payment to avoid service suspension.')
            ->line("Grace period ends on {$this->subscription->ends_at?->copy()->addDays(3)->toDayDateTimeString()}.");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Subscription past due',
            'body' => 'Your subscription is past due and has entered the 3-day grace period.',
            'subscription' => [
                'id' => $this->subscription->id,
                'status' => $this->subscription->status->value,
                'billing_cycle' => $this->subscription->billing_cycle->value,
                'ends_at' => $this->subscription->ends_at?->toISOString(),
                'grace_ends_at' => $this->subscription->ends_at?->copy()->addDays(3)->toISOString(),
            ],
            'company' => [
                'id' => $this->subscription->company?->id,
                'name' => $this->subscription->company?->name,
            ],
            'plan' => [
                'id' => $this->subscription->plan?->id,
                'name' => $this->subscription->plan?->name,
            ],
        ];
    }
}
