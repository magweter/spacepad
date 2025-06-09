<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Provider;

class InstanceHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instanceId' => ['required', 'string'],
            'licenseKey' => ['nullable', 'string'],
            'isSelfHosted' => ['required', 'boolean'],
            'version' => ['required', 'string'],
            'accounts' => ['required', 'array'],
            'users' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'instanceId.required' => 'The instance ID is required.',
            'licenseKey.required' => 'The license key is required.',
            'version.required' => 'The version is required.',
            'accounts.required' => 'The accounts array is required.',
            'users.required' => 'The users array is required.',
        ];
    }
}
