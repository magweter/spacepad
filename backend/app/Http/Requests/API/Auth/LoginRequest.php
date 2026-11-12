<?php

namespace App\Http\Requests\API\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Brute-force protection lives on the route (throttle:device_pairing), not here.
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string',
            'uid' => 'required|string',
            'name' => 'required|string',
        ];
    }
}
