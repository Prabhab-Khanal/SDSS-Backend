<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorizationTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token'      => $this->token,
            'file_id'    => $this->file_id,
            'expires_at' => $this->expires_at,
        ];
    }
}
