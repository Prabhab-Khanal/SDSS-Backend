<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'file'             => $this->whenLoaded('file', fn () => [
                'id'    => $this->file->id,
                'name'  => $this->file->name,
                'owner' => $this->when($this->file->relationLoaded('user'), fn () => [
                    'id'         => $this->file->user->id,
                    'first_name' => $this->file->user->first_name,
                    'last_name'  => $this->file->user->last_name,
                ]),
            ]),
            'requester'        => $this->whenLoaded('requester', fn () => [
                'id'         => $this->requester->id,
                'first_name' => $this->requester->first_name,
                'last_name'  => $this->requester->last_name,
            ]),
            'status'           => $this->status,
            'message'          => $this->message,
            'rejection_reason' => $this->rejection_reason,
            'created_at'       => $this->created_at,
            'resolved_at'      => $this->resolved_at,
        ];
    }
}
