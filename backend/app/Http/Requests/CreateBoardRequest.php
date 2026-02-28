<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBoardRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'workspace_id' => 'required|string|exists:workspaces,id',
            'show_all_displays' => 'required|boolean',
            'theme' => 'nullable|string|in:dark,light,system',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'remove_logo' => 'nullable|boolean',
            'display_ids' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    $showAll = filter_var(request()->input('show_all_displays'), FILTER_VALIDATE_BOOLEAN);
                    if (!$showAll && (empty($value) || !is_array($value) || count($value) === 0)) {
                        $fail('Please select at least one display when not showing all displays.');
                    }
                },
            ],
            'display_ids.*' => 'exists:displays,id',
        ];
    }

    public function messages(): array
    {
        return [
            'display_ids.required_if' => 'Please select at least one display when not showing all displays.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert string "1" or "0" to boolean
        if ($this->has('show_all_displays')) {
            $this->merge([
                'show_all_displays' => filter_var($this->show_all_displays, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Ensure display_ids is an array
        if ($this->has('display_ids') && !is_array($this->display_ids)) {
            $this->merge([
                'display_ids' => $this->display_ids ? [$this->display_ids] : [],
            ]);
        }
    }
}
