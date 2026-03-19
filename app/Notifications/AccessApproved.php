<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use App\Models\AuthorizationToken;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccessApproved extends Notification
{
    use Queueable;

    public function __construct(
        private AccessRequest $accessRequest,
        private AuthorizationToken $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message'    => $this->accessRequest->file->user->full_name . ' approved your access to ' . $this->accessRequest->file->name,
            'file'       => [
                'id'   => $this->accessRequest->file->id,
                'name' => $this->accessRequest->file->name,
            ],
            'token'      => $this->token->token,
            'expires_at' => $this->token->expires_at->toIso8601String(),
        ];
    }
}
