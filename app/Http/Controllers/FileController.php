<?php

namespace App\Http\Controllers;

use App\Http\Requests\File\MoveFileRequest;
use App\Http\Requests\File\StoreFileRequest;
use App\Http\Requests\File\UpdateFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\Folder;
use App\Services\AuditLogService;
use App\Services\FileStorageService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(
        private FileStorageService $fileStorage,
        private AuditLogService $auditLog
    ) {}

    #[OA\Get(
        path: '/api/files',
        summary: 'List files in root (no folder)',
        tags: ['Files'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Files retrieved'),
        ]
    )]
    public function index(): JsonResponse
    {
        $files = File::where('user_id', auth('api')->id())
            ->whereNull('folder_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Files retrieved',
            'data'    => FileResource::collection($files),
        ]);
    }

    #[OA\Post(
        path: '/api/files',
        summary: 'Upload a file',
        description: 'Upload a file using multipart/form-data. In Postman: set Body to form-data, add key "file" with type File, optionally add "folder_id" as Text.',
        tags: ['Files'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'File to upload (max 50MB)'),
                        new OA\Property(property: 'folder_id', type: 'integer', nullable: true, description: 'Target folder ID (null = root)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'File uploaded'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreFileRequest $request): JsonResponse
    {
        $user   = auth('api')->user();
        $folder = null;

        if ($request->folder_id) {
            $folder = Folder::where('id', $request->folder_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$folder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Folder not found or not owned by you.',
                ], 422);
            }
        }

        $file = $this->fileStorage->store($request->file('file'), $user, $folder);

        $this->auditLog->log('file.uploaded', $file, [
            'file_name' => $file->name,
            'size'      => $file->size,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded',
            'data'    => new FileResource($file),
        ], 201);
    }

    #[OA\Patch(
        path: '/api/files/{file}',
        summary: 'Rename a file',
        tags: ['Files'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [new OA\Property(property: 'name', type: 'string', example: 'new-name.pdf')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'File renamed'),
            new OA\Response(response: 422, description: 'Duplicate name'),
        ]
    )]
    public function update(UpdateFileRequest $request, File $file): JsonResponse
    {
        $this->authorize('update', $file);

        $exists = File::where('user_id', $file->user_id)
            ->where('folder_id', $file->folder_id)
            ->where('name', $request->name)
            ->where('id', '!=', $file->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A file with this name already exists in this folder.',
            ], 422);
        }

        $file->update(['name' => $request->name]);

        $this->auditLog->log('file.renamed', $file);

        return response()->json([
            'success' => true,
            'message' => 'File renamed',
            'data'    => new FileResource($file),
        ]);
    }

    #[OA\Patch(
        path: '/api/files/{file}/move',
        summary: 'Move a file to another folder',
        tags: ['Files'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [new OA\Property(property: 'folder_id', type: 'integer', nullable: true, description: 'Target folder ID (null = root)')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'File moved'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function move(MoveFileRequest $request, File $file): JsonResponse
    {
        $this->authorize('update', $file);

        if ($request->folder_id) {
            $folder = Folder::where('id', $request->folder_id)
                ->where('user_id', auth('api')->id())
                ->first();

            if (!$folder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Target folder not found or not owned by you.',
                ], 422);
            }
        }

        $exists = File::where('user_id', $file->user_id)
            ->where('folder_id', $request->folder_id)
            ->where('name', $file->name)
            ->where('id', '!=', $file->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A file with this name already exists in the target folder.',
            ], 422);
        }

        $file->update(['folder_id' => $request->folder_id]);

        $this->auditLog->log('file.moved', $file);

        return response()->json([
            'success' => true,
            'message' => 'File moved',
            'data'    => new FileResource($file),
        ]);
    }

    #[OA\Delete(
        path: '/api/files/{file}',
        summary: 'Delete a file',
        tags: ['Files'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'File deleted'),
        ]
    )]
    public function destroy(File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        $this->auditLog->log('file.deleted', $file, ['name' => $file->name]);

        $file->authorizationTokens()
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['revoked_at' => now()]);

        $file->accessRequests()
            ->where('status', 'pending')
            ->update(['status' => 'rejected', 'resolved_at' => now()]);

        $this->fileStorage->delete($file);

        return response()->json([
            'success' => true,
            'message' => 'File deleted',
        ]);
    }

    #[OA\Get(
        path: '/api/files/{file}/download',
        summary: 'Download a file',
        tags: ['Files'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'File stream'),
        ]
    )]
    public function download(File $file): StreamedResponse
    {
        $this->authorize('download', $file);

        $this->auditLog->log('file.downloaded', $file);

        return $this->fileStorage->streamForDownload($file);
    }
}
