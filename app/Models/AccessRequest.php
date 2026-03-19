<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccessRequest extends Model
{
    protected $fillable = [
        'requester_id',
        'file_id',
        'status',
        'message',
        'rejection_reason',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function authorizationToken(): HasOne
    {
        return $this->hasOne(AuthorizationToken::class);
    }

    public function scopePending($q)  { return $q->where('status', 'pending'); }
    public function scopeApproved($q) { return $q->where('status', 'approved'); }
    public function scopeRejected($q) { return $q->where('status', 'rejected'); }
}
