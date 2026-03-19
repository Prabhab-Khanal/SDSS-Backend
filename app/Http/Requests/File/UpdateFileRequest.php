<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\\.\.]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'File name cannot contain path traversal characters.',
        ];
    }
}
