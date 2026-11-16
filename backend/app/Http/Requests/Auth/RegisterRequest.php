<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'nullable|string|max:255',
            'email' => 'required|string|email',
            'g-recaptcha-response' => config('recaptchav3.sitekey') ? 'required|recaptchav3:register,0.5' : 'nullable',
        ];

        return $rules;
    }
}
