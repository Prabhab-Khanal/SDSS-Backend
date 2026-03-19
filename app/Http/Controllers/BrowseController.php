<?php

namespace App\Http\Controllers;

use App\Http\Resources\BrowseFileResource;
use App\Http\Resources\BrowseUserResource;
use App\Http\Resources\FolderResource;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BrowseController extends Controller
{
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
