<?php

namespace App\Http\Requests\AccessRequest;

use Illuminate\Foundation\Http\FormRequest;

class RejectAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
