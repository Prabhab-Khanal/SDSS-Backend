<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccessRequested extends Notification
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
            'message'           => $this->accessRequest->requester->full_name . ' requested access to ' . $this->accessRequest->file->name,
            'requester'         => [
                'id'         => $this->accessRequest->requester->id,
                'first_name' => $this->accessRequest->requester->first_name,
                'last_name'  => $this->accessRequest->requester->last_name,
            ],
            'file'              => [
                'id'   => $this->accessRequest->file->id,
                'name' => $this->accessRequest->file->name,
            ],
            'access_request_id' => $this->accessRequest->id,
        ];
    }
}
