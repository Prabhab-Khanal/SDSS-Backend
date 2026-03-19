<?php

namespace App\Policies;

use App\Models\AccessRequest;
use App\Models\User;

class AccessRequestPolicy
{
    public function approve(User $user, AccessRequest $accessRequest): bool
    {
        return $user->id === $accessRequest->file->user_id;
    }

    public function reject(User $user, AccessRequest $accessRequest): bool
    {
        return $user->id === $accessRequest->file->user_id;
    }
}
