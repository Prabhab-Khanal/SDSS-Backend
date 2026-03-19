<?php

namespace App\Http\Controllers;

use App\Http\Requests\Folder\MoveFolderRequest;
use App\Http\Requests\Folder\StoreFolderRequest;
use App\Http\Requests\Folder\UpdateFolderRequest;
use App\Http\Resources\FileResource;
use App\Http\Resources\FolderResource;
use App\Models\Folder;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class FolderController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog
    ) {}

    #[OA\Get(
        path: '/api/folders',
        summary: 'List root folders',
        tags: ['Folders'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Folders retrieved'),
        ]
    )]
    public function index(): JsonResponse
    {
        $folders = Folder::where('user_id', auth('api')->id())
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Folders retrieved',
            'data'    => FolderResource::collection($folders),
        ]);
    }

    #[OA\Get(
        path: '/api/folders/{folder}',
        summary: 'Get folder contents (subfolders + files)',
        tags: ['Folders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'folder', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Folder contents'),
        ]
    )]
    public function show(Folder $folder): JsonResponse
    {
        $this->authorize('view', $folder);

        return response()->json([
            'success' => true,
            'message' => 'Folder contents',
            'data'    => [
                'folder'     => new FolderResource($folder),
                'subfolders' => FolderResource::collection($folder->children()->orderBy('name')->get()),
                'files'      => FileResource::collection($folder->files()->orderBy('name')->get()),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/folders',
        summary: 'Create a new folder',
        tags: ['Folders'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'My Documents'),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true, description: 'Parent folder ID (null = root)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Folder created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreFolderRequest $request): JsonResponse
    {
        $userId = auth('api')->id();

        if ($request->parent_id) {
            $parent = Folder::where('id', $request->parent_id)
                ->where('user_id', $userId)
                ->first();

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent folder not found or not owned by you.',
                ], 422);
            }
        }

        $exists = Folder::where('user_id', $userId)
            ->where('parent_id', $request->parent_id)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A folder with this name already exists at this level.',
            ], 422);
        }

        $folder = Folder::create([
            'user_id'   => $userId,
            'parent_id' => $request->parent_id,
            'name'      => $request->name,
        ]);

        $this->auditLog->log('folder.created', $folder);

        return response()->json([
            'success' => true,
            'message' => 'Folder created',
            'data'    => new FolderResource($folder),
        ], 201);
    }

    #[OA\Patch(
        path: '/api/folders/{folder}',
        summary: 'Rename a folder',
        tags: ['Folders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'folder', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [new OA\Property(property: 'name', type: 'string', example: 'Renamed Folder')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Folder renamed'),
            new OA\Response(response: 422, description: 'Duplicate name'),
        ]
    )]
    public function update(UpdateFolderRequest $request, Folder $folder): JsonResponse
    {
        $this->authorize('update', $folder);

        $exists = Folder::where('user_id', $folder->user_id)
            ->where('parent_id', $folder->parent_id)
            ->where('name', $request->name)
            ->where('id', '!=', $folder->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A folder with this name already exists at this level.',
            ], 422);
        }

        $folder->update(['name' => $request->name]);

        $this->auditLog->log('folder.renamed', $folder);

        return response()->json([
            'success' => true,
            'message' => 'Folder renamed',
            'data'    => new FolderResource($folder),
        ]);
    }

    #[OA\Patch(
        path: '/api/folders/{folder}/move',
        summary: 'Move folder to a new parent',
        tags: ['Folders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'folder', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [new OA\Property(property: 'parent_id', type: 'integer', nullable: true, description: 'Target parent folder ID (null = root)')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Folder moved'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function move(MoveFolderRequest $request, Folder $folder): JsonResponse
    {
        $this->authorize('update', $folder);

        $targetParentId = $request->parent_id;

        if ($targetParentId == $folder->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot move a folder into itself.',
            ], 422);
        }

        if ($targetParentId) {
            $targetParent = Folder::where('id', $targetParentId)
                ->where('user_id', auth('api')->id())
                ->first();

            if (!$targetParent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Target folder not found or not owned by you.',
                ], 422);
            }

            if ($targetParent->isDescendantOf($folder->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move a folder into one of its own subfolders.',
                ], 422);
            }
        }

        $exists = Folder::where('user_id', $folder->user_id)
            ->where('parent_id', $targetParentId)
            ->where('name', $folder->name)
            ->where('id', '!=', $folder->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A folder with this name already exists at the target level.',
            ], 422);
        }

        $folder->update(['parent_id' => $targetParentId]);

        $this->auditLog->log('folder.moved', $folder);

        return response()->json([
            'success' => true,
            'message' => 'Folder moved',
            'data'    => new FolderResource($folder),
        ]);
    }

    #[OA\Delete(
        path: '/api/folders/{folder}',
        summary: 'Delete a folder and its contents',
        tags: ['Folders'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'folder', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Folder deleted'),
        ]
    )]
    public function destroy(Folder $folder): JsonResponse
    {
        $this->authorize('delete', $folder);

        $this->auditLog->log('folder.deleted', $folder, ['name' => $folder->name]);

        $folder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Folder deleted',
        ]);
    }
}
