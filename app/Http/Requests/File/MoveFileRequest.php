<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class MoveFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ];
    }
}
