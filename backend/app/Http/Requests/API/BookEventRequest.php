<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class BookEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duration' => 'required|in:15,30,45,60',
            'summary' => 'nullable|string|max:255',
        ];
    }
}
