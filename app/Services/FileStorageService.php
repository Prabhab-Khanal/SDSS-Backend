<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use App\Models\Folder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileStorageService
{
    public function store(UploadedFile $uploadedFile, User $owner, ?Folder $folder = null): File
    {
        $extension   = $uploadedFile->getClientOriginalExtension();
        $storagePath = sprintf('users/%d/%s.%s', $owner->id, Str::uuid(), $extension);

        Storage::disk('local')->put($storagePath, file_get_contents($uploadedFile->getRealPath()));

        return File::create([
            'user_id'       => $owner->id,
            'folder_id'     => $folder?->id,
            'name'          => $uploadedFile->getClientOriginalName(),
            'original_name' => $uploadedFile->getClientOriginalName(),
            'storage_path'  => $storagePath,
            'mime_type'     => $uploadedFile->getClientMimeType(),
            'size'          => $uploadedFile->getSize(),
        ]);
    }

    public function delete(File $file): void
    {
        Storage::disk('local')->delete($file->storage_path);
        $file->delete();
    }

    public function streamForDownload(File $file): StreamedResponse
    {
        return Storage::disk('local')->download($file->storage_path, $file->name);
    }
}
