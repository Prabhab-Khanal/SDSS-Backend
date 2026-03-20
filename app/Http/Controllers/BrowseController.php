<?php

namespace App\Http\Controllers;

use App\Http\Resources\BrowseFileResource;
use App\Http\Resources\BrowseUserResource;
use App\Http\Resources\FolderResource;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BrowseController extends Controller
{
    #[OA\Get(
        path: '/api/browse/users',
        summary: 'List all browsable users (excludes self and admins)',
        tags: ['Browse'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Users retrieved'),
        ]
    )]
    public function users(): JsonResponse
    {
        $users = User::approved()
            ->where('id', '!=', auth('api')->id())
            ->where('role', '!=', 'admin')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved',
            'data'    => BrowseUserResource::collection($users),
        ]);
    }

    #[OA\Get(
        path: '/api/browse/users/{user}/folders',
        summary: 'List root folders of a user',
        tags: ['Browse'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User folders retrieved'),
        ]
    )]
    public function folders(User $user): JsonResponse
    {
        $folders = Folder::where('user_id', $user->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'User folders retrieved',
            'data'    => FolderResource::collection($folders),
        ]);
    }

    #[OA\Get(
        path: '/api/browse/users/{user}/folders/{folder}',
        summary: 'Get contents of a specific folder of a user',
        tags: ['Browse'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'folder', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Folder contents retrieved'),
            new OA\Response(response: 404, description: 'Folder does not belong to this user'),
        ]
    )]
    public function folderContents(User $user, Folder $folder): JsonResponse
    {
        if ($folder->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Folder does not belong to this user.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Folder contents retrieved',
            'data'    => [
                'folder'     => new FolderResource($folder),
                'subfolders' => FolderResource::collection($folder->children()->orderBy('name')->get()),
                'files'      => BrowseFileResource::collection($folder->files()->orderBy('name')->get()),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/browse/users/{user}/files',
        summary: 'List root-level files of a user',
        tags: ['Browse'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User files retrieved'),
        ]
    )]
    public function files(User $user): JsonResponse
    {
        $files = File::where('user_id', $user->id)
            ->whereNull('folder_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'User files retrieved',
            'data'    => BrowseFileResource::collection($files),
        ]);
    }
}
