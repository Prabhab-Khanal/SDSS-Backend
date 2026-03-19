<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrowseFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'mime_type'  => $this->mime_type,
            'size'       => $this->size,
            'created_at' => $this->created_at,
            // No storage_path, no original_name, no download URL
        ];
    }
}
