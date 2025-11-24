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
            'duration' => 'required_without:start|in:15,30,45,60',
            'start' => 'required_without:duration|date',
            'end' => 'required_with:start|date|after:start',
            'summary' => 'nullable|string|max:255',
        ];
    }
}
