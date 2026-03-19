<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function update(array $attributes = [], array $options = []): never
    {
        throw new \LogicException('Audit logs are immutable and cannot be updated.');
    }

    public function delete(): never
    {
        throw new \LogicException('Audit logs are immutable and cannot be deleted.');
    }
}
