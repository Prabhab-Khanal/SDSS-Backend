<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'      => ['required', 'file', 'max:51200'], // 50MB
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ];
    }
}
