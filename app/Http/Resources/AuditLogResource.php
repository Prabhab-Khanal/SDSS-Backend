<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'user'          => $this->whenLoaded('user', fn () => [
                'id'         => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name'  => $this->user->last_name,
                'email'      => $this->user->email,
            ]),
            'action'        => $this->action,
            'resource_type' => $this->resource_type,
            'resource_id'   => $this->resource_id,
            'metadata'      => $this->metadata,
            'ip_address'    => $this->ip_address,
            'created_at'    => $this->created_at,
        ];
    }
}
