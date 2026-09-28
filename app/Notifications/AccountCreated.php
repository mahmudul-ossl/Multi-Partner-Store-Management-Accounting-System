<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->name ?? 'there';

        return (new MailMessage)
            ->subject('Your '.config('app.name').' account is ready')
            ->greeting('Hello '.$name.',')
            ->line('An administrator created a system account for you.')
            ->action('Sign in', url('/login'))
            ->line('If you were not expecting this, contact your store administrator.');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account created',
            'message' => 'Your system account has been created.',
        ];
    }
}
