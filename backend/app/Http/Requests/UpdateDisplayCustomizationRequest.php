<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisplayCustomizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller
        return true;
    }

    public function rules(): array
    {
        return [
            'text_available' => 'nullable|string|max:64',
            'text_transitioning' => 'nullable|string|max:64',
            'text_reserved' => 'nullable|string|max:64',
            'text_checkin' => 'nullable|string|max:64',
            'show_meeting_title' => 'boolean',
        ];
    }
} 