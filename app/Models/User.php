<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = ['first_name', 'last_name', 'email', 'password', 'status', 'role'];
    protected $hidden   = ['password'];
    protected $casts    = ['created_at' => 'datetime', 'updated_at' => 'datetime'];

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = bcrypt($value);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function scopeApproved($q) { return $q->where('status', 'approved'); }
    public function scopePending($q)  { return $q->where('status', 'pending'); }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(AccessRequest::class, 'requester_id');
    }

    public function authorizationTokens(): HasMany
    {
        return $this->hasMany(AuthorizationToken::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role'   => $this->role,
            'status' => $this->status,
        ];
    }
}
