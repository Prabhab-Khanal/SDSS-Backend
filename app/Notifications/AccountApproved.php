<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountApproved extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Your account has been approved. You can now log in.',
        ];
    }
}
