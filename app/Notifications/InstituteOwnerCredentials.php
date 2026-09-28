<?php

namespace App\Notifications;

use App\Models\Institute;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends a new franchise owner their institute panel login.
 */
class InstituteOwnerCredentials extends Notification
{
    use Queueable;

    public function __construct(public Institute $institute, public string $password) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Welcome to KCTI — {$this->institute->name} ({$this->institute->code})")
            ->greeting("Namaste {$notifiable->name},")
            ->line("Your franchise {$this->institute->name} is now active with code {$this->institute->code}.")
            ->line("Login email: {$notifiable->email}")
            ->line("Temporary password: {$this->password}")
            ->action('Open institute panel', url('/institute/login'))
            ->line('Please change your password after your first login.');
    }
}
