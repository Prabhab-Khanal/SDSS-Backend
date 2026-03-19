<?php

namespace App\Http\Requests\Folder;

use Illuminate\Foundation\Http\FormRequest;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\\.\.]+$/'],
            'parent_id' => ['nullable', 'integer', 'exists:folders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Folder name cannot contain path traversal characters.',
        ];
    }
}
