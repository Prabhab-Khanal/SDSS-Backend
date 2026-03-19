<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class StorageController extends Controller
{
    #[OA\Get(
        path: '/api/my-storage',
        summary: 'Get full storage tree with nested folders/files and storage stats',
        tags: ['Storage'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Storage tree retrieved'),
        ]
    )]
    public function index(): JsonResponse
    {
        $userId = auth('api')->id();

        // Get all folders and files for this user in one query each
        $allFolders = Folder::where('user_id', $userId)
            ->orderBy('name')
            ->get();

        $allFiles = File::where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Build nested tree
        $tree = $this->buildTree($allFolders, $allFiles);

        // Recent files (last 10, across all folders)
        $recent = $allFiles->take(10)->map(fn ($f) => [
            'id'            => $f->id,
            'name'          => $f->name,
            'mime_type'     => $f->mime_type,
            'size'          => $f->size,
            'folder_id'     => $f->folder_id,
            'folder_name'   => $f->folder_id ? $allFolders->firstWhere('id', $f->folder_id)?->name : null,
            'updated_at'    => $f->updated_at,
        ]);

        // Storage stats
        $stats = [
            'total_files'   => $allFiles->count(),
            'total_folders' => $allFolders->count(),
            'total_size'    => $allFiles->sum('size'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Storage retrieved',
            'data'    => [
                'tree'   => $tree,
                'recent' => $recent->values(),
                'stats'  => $stats,
            ],
        ]);
    }

    /**
     * Build nested folder/file tree recursively.
     */
    private function buildTree($allFolders, $allFiles, ?int $parentId = null): array
    {
        $items = [];

        // Add folders at this level
        $folders = $allFolders->where('parent_id', $parentId);
        foreach ($folders as $folder) {
            $children = $this->buildTree($allFolders, $allFiles, $folder->id);

            // Calculate folder size (sum of all files in this folder + subfolders recursively)
            $folderSize = $this->calculateFolderSize($children, $allFiles, $folder->id);

            $items[] = [
                'id'         => $folder->id,
                'name'       => $folder->name,
                'type'       => 'folder',
                'parent_id'  => $folder->parent_id,
                'size'       => $folderSize,
                'children'   => $children,
                'created_at' => $folder->created_at,
                'updated_at' => $folder->updated_at,
            ];
        }

        // Add files at this level
        $files = $allFiles->where('folder_id', $parentId);
        foreach ($files as $file) {
            $items[] = [
                'id'            => $file->id,
                'name'          => $file->name,
                'type'          => 'file',
                'mime_type'     => $file->mime_type,
                'size'          => $file->size,
                'original_name' => $file->original_name,
                'folder_id'     => $file->folder_id,
                'created_at'    => $file->created_at,
                'updated_at'    => $file->updated_at,
            ];
        }

        return $items;
    }

    /**
     * Calculate total size of files in a folder (direct files only, children already have their own size).
     */
    private function calculateFolderSize(array $children, $allFiles, int $folderId): int
    {
        $directSize = $allFiles->where('folder_id', $folderId)->sum('size');

        $childFolderSize = collect($children)
            ->where('type', 'folder')
            ->sum('size');

        return $directSize + $childFolderSize;
    }
}
