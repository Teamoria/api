<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCanceledNotification extends Notification implements ShouldQueue
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
        return 'subscription_canceled';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your current plan';
        $companyName = $this->subscription->company?->name ?? 'your company';

        return (new MailMessage)
            ->subject('Your Teamoria subscription has been canceled')
            ->greeting("Hello {$notifiable->name},")
            ->line("The {$companyName} subscription for {$planName} has been canceled because the grace period ended without payment confirmation.")
            ->line('Workspace access is now suspended until a new payment is approved by an administrator.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Subscription canceled',
            'body' => 'Your subscription was canceled after the 3-day grace period ended.',
            'subscription' => [
                'id' => $this->subscription->id,
                'status' => $this->subscription->status->value,
                'billing_cycle' => $this->subscription->billing_cycle->value,
                'ends_at' => $this->subscription->ends_at?->toISOString(),
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
