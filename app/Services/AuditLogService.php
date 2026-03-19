<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function log(string $action, ?Model $resource = null, array $metadata = []): AuditLog
    {
        return AuditLog::create([
            'user_id'       => auth('api')->id(),
            'action'        => $action,
            'resource_type' => $resource ? $this->getResourceType($resource) : null,
            'resource_id'   => $resource?->getKey(),
            'metadata'      => $metadata ?: null,
            'ip_address'    => request()->ip(),
        ]);
    }

    private function getResourceType(Model $resource): string
    {
        return strtolower(class_basename($resource));
    }
}
