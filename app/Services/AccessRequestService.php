<?php

namespace App\Services;

use App\Models\AccessRequest;
use App\Models\AuthorizationToken;
use App\Models\File;
use App\Models\User;
use App\Notifications\AccessApproved;
use App\Notifications\AccessRejected;
use App\Notifications\AccessRequested;

class AccessRequestService
{
    public function __construct(
        private AuthorizationTokenService $tokenService,
        private AuditLogService $auditLog
    ) {}

    public function createRequest(User $requester, File $file, ?string $message = null): AccessRequest
    {
        // Check for duplicate pending request
        $existing = AccessRequest::where('requester_id', $requester->id)
            ->where('file_id', $file->id)
            ->pending()
            ->first();

        if ($existing) {
            abort(409, 'You already have a pending access request for this file.');
        }

        // Cannot request access to own file
        if ($file->user_id === $requester->id) {
            abort(403, 'You cannot request access to your own file.');
        }

        $request = AccessRequest::create([
            'requester_id' => $requester->id,
            'file_id'      => $file->id,
            'status'       => 'pending',
            'message'      => $message,
        ]);

        $request->load(['requester', 'file']);

        // Notify file owner
        $file->user->notify(new AccessRequested($request));

        $this->auditLog->log('access.requested', $request, [
            'file_name'  => $file->name,
            'file_owner' => $file->user_id,
        ]);

        return $request;
    }

    public function approve(AccessRequest $accessRequest): AuthorizationToken
    {
        $accessRequest->update([
            'status'      => 'approved',
            'resolved_at' => now(),
        ]);

        $token = $this->tokenService->generate($accessRequest);

        // Notify requester
        $accessRequest->load(['file.user']);
        $accessRequest->requester->notify(new AccessApproved($accessRequest, $token));

        $this->auditLog->log('access.approved', $accessRequest);
        $this->auditLog->log('token.generated', $token, [
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);

        return $token;
    }

    public function reject(AccessRequest $accessRequest, ?string $reason = null): void
    {
        $accessRequest->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'resolved_at'      => now(),
        ]);

        // Notify requester
        $accessRequest->load(['file.user']);
        $accessRequest->requester->notify(new AccessRejected($accessRequest));

        $this->auditLog->log('access.rejected', $accessRequest);
    }
}
