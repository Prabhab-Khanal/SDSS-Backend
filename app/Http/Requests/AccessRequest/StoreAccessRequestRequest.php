<?php

namespace App\Http\Requests\AccessRequest;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
