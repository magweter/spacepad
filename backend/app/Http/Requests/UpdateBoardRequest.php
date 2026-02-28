<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardRequest extends FormRequest
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
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'workspace_id' => 'required|string|exists:workspaces,id',
            'show_all_displays' => 'required|boolean',
            'theme' => 'nullable|string|in:dark,light,system',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'remove_logo' => 'nullable|boolean',
            'show_title' => 'nullable|boolean',
            'show_booker' => 'nullable|boolean',
            'show_next_event' => 'nullable|boolean',
            'show_transitioning' => 'nullable|boolean',
            'transitioning_minutes' => 'nullable|integer|min:1|max:60',
            'font_family' => 'nullable|string|in:Inter,Roboto,Open Sans,Lato,Poppins,Montserrat',
            'language' => 'nullable|string|in:en,nl,fr,de,es,sv',
            'view_mode' => 'nullable|string|in:card,table,grid',
            'show_meeting_title' => 'nullable|boolean',
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

        // Convert checkbox values to boolean (if checkbox is unchecked, it won't be in request, so default to false)
        $this->merge([
            'show_title' => $this->has('show_title') && filter_var($this->show_title, FILTER_VALIDATE_BOOLEAN),
            'show_booker' => $this->has('show_booker') && filter_var($this->show_booker, FILTER_VALIDATE_BOOLEAN),
            'show_next_event' => $this->has('show_next_event') && filter_var($this->show_next_event, FILTER_VALIDATE_BOOLEAN),
            'show_transitioning' => $this->has('show_transitioning') && filter_var($this->show_transitioning, FILTER_VALIDATE_BOOLEAN),
            'transitioning_minutes' => $this->has('transitioning_minutes') ? (int) $this->transitioning_minutes : ($board?->transitioning_minutes ?? 10),
            'show_meeting_title' => $this->has('show_meeting_title') && filter_var($this->show_meeting_title, FILTER_VALIDATE_BOOLEAN),
        ]);

        // Ensure display_ids is an array
        if ($this->has('display_ids') && !is_array($this->display_ids)) {
            $this->merge([
                'display_ids' => $this->display_ids ? [$this->display_ids] : [],
            ]);
        }
    }
}
