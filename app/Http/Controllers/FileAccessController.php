<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\AuthorizationTokenService;
use App\Services\FileStorageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileAccessController extends Controller
{
    public function __construct(
        private AuthorizationTokenService $tokenService,
        private FileStorageService $fileStorage,
        private AuditLogService $auditLog
    ) {}

    public function download(string $token): StreamedResponse|JsonResponse
    {
        $authToken = $this->tokenService->validate($token);

        // Verify token belongs to authenticated user
        if ($authToken->user_id !== auth('api')->id()) {
            $this->auditLog->log('file.unauthorized_access_attempt', $authToken->file, [
                'token_id'       => $authToken->id,
                'token_owner_id' => $authToken->user_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token does not belong to you.',
            ], 403);
        }

        // Consume token BEFORE streaming (security-first)
        $this->tokenService->consume($authToken);

        $this->auditLog->log('file.accessed_via_token', $authToken->file, [
            'token_id' => $authToken->id,
        ]);

        return $this->fileStorage->streamForDownload($authToken->file);
    }
}
