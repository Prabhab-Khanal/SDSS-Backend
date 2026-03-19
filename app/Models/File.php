<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends Model
{
    protected $fillable = [
        'user_id',
        'folder_id',
        'name',
        'original_name',
        'storage_path',
        'mime_type',
        'size',
    ];

    protected $hidden = ['storage_path'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(AccessRequest::class);
    }

    public function authorizationTokens(): HasMany
    {
        return $this->hasMany(AuthorizationToken::class);
    }
}
