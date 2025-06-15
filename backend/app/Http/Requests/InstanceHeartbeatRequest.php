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
            'instance_key' => ['required', 'string'],
            'license_key' => ['nullable', 'string'],
            'license_valid' => ['nullable', 'boolean'],
            'license_expires_at' => ['nullable', 'date'],
            'is_self_hosted' => ['required', 'boolean'],
            'displays_count' => ['required', 'integer', 'min:0'],
            'rooms_count' => ['required', 'integer', 'min:0'],
            'version' => ['required', 'string'],
            'users' => ['required', 'array'],
            'users.*.email' => ['required', 'email'],
            'users.*.usage_type' => ['nullable', 'string'],
            'users.*.is_unlimited' => ['nullable', 'boolean'],
            'users.*.terms_accepted_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'instance_key.required' => 'The instance key is required.',
            'license_key.required' => 'The license key is required.',
            'license_valid.boolean' => 'The license valid flag must be a boolean.',
            'license_expires_at.date' => 'The license expiration date must be a valid date.',
            'is_self_hosted.required' => 'The self-hosted flag is required.',
            'displays_count.required' => 'The displays count is required.',
            'displays_count.integer' => 'The displays count must be an integer.',
            'displays_count.min' => 'The displays count cannot be negative.',
            'rooms_count.required' => 'The rooms count is required.',
            'rooms_count.integer' => 'The rooms count must be an integer.',
            'rooms_count.min' => 'The rooms count cannot be negative.',
            'version.required' => 'The version is required.',
            'users.required' => 'The users array is required.',
            'users.*.email.required' => 'Each user must have an email address.',
            'users.*.email.email' => 'Each user must have a valid email address.',
            'users.*.usage_type.string' => 'The usage type must be a string.',
            'users.*.is_unlimited.boolean' => 'The unlimited flag must be a boolean.',
            'users.*.terms_accepted_at.date' => 'The terms accepted date must be a valid date.',
        ];
    }
}
