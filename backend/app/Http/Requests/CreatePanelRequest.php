<?php

namespace App\Http\Requests;

use App\Enums\DisplayMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePanelRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'display_mode' => ['required', Rule::enum(DisplayMode::class)],
            'displays' => ['required', 'array', 'min:1', 'max:4'],
            'displays.*' => ['required', 'ulid', 'exists:displays,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('displays')) {
                $displayIds = $this->input('displays');
                $userDisplayIds = \App\Models\Display::where('user_id', auth()->id())
                    ->whereIn('id', $displayIds)
                    ->pluck('id')
                    ->toArray();

                if (count($userDisplayIds) !== count($displayIds)) {
                    $validator->errors()->add('displays', 'One or more displays do not belong to you.');
                }
            }
        });
    }
}

