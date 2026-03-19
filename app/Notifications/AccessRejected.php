<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccessRejected extends Notification
{
    use Queueable;

    public function __construct(
        private AccessRequest $accessRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message'          => $this->accessRequest->file->user->full_name . ' rejected your access request for ' . $this->accessRequest->file->name,
            'file'             => [
                'id'   => $this->accessRequest->file->id,
                'name' => $this->accessRequest->file->name,
            ],
            'rejection_reason' => $this->accessRequest->rejection_reason,
        ];
    }
}
