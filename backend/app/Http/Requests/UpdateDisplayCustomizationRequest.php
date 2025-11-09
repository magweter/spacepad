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
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'default_background' => 'nullable|string|in:default_1,default_2,default_3,default_4,default_5,default_6,default_7,default_8',
            'remove_logo' => 'boolean',
            'remove_background_image' => 'boolean',
            'font_family' => 'nullable|string|in:Inter,Roboto,Open Sans,Lato,Poppins,Montserrat',
        ];
    }
} 