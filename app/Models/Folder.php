<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    protected $fillable = ['user_id', 'parent_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Check if a given folder is a descendant of this folder (to prevent circular moves).
     */
    public function isDescendantOf(int $ancestorId): bool
    {
        $current = $this->parent_id;

        while ($current !== null) {
            if ($current === $ancestorId) {
                return true;
            }
            $current = Folder::where('id', $current)->value('parent_id');
        }

        return false;
    }
}
