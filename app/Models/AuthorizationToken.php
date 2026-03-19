<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationToken extends Model
{
    protected $fillable = [
        'token',
        'access_request_id',
        'user_id',
        'file_id',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function accessRequest(): BelongsTo
    {
        return $this->belongsTo(AccessRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture()
            && is_null($this->used_at)
            && is_null($this->revoked_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return !is_null($this->used_at);
    }

    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }

    public function scopeValid($q)
    {
        return $q->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->whereNull('revoked_at');
    }
}
