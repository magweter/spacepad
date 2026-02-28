<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateDisplayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'displayName' => 'required|string',
            'account' => 'required|string',
            'provider' => 'required|string|in:outlook,google,caldav',
            'room' => 'required_without:calendar|string',
            'calendar' => 'required_without:room|string',
            'workspace_id' => 'nullable|string|exists:workspaces,id',
        ];
    }
}
