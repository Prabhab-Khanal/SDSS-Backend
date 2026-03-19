<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'original_name' => $this->original_name,
            'mime_type'     => $this->mime_type,
            'size'          => $this->size,
            'folder_id'     => $this->folder_id,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            // storage_path is NEVER included
        ];
    }
}
