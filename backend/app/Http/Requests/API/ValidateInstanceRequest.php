<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Provider;

class ValidateInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_key' => ['required', 'string'],
            'license_key' => ['required', 'string'],
        ];
    }
}
