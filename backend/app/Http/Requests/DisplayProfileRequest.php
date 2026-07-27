<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisplayProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Pro access and workspace management rights are enforced in the controller.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspaceId = auth()->user()?->getSelectedWorkspace()?->id;

        return [
            'name' => 'required|string|max:255',

            // Behavioral toggles
            'check_in_enabled' => 'boolean',
            'booking_enabled' => 'boolean',
            'hide_admin_actions' => 'boolean',
            'view_schedule' => 'boolean',
            'allow_future_bookings' => 'boolean',
            'extend_enabled' => 'boolean',
            'show_organizer' => 'boolean',
            'show_meeting_title' => 'boolean',
            'advertisement_enabled' => 'boolean',

            // Numeric
            'check_in_minutes' => 'nullable|integer|min:1|max:60',
            'check_in_grace_period' => 'nullable|integer|min:1|max:30',
            'advertisement_interval' => 'nullable|integer|min:1|max:60',
            'advertisement_duration' => 'nullable|integer|min:1|max:120',

            // Enumerated / string
            'timeline_widget_mode' => 'nullable|in:none,side_panel,inline,full_panel',
            'cancel_permission' => 'nullable|in:all,tablet_only,none',
            'border_thickness' => 'nullable|in:small,medium,large',
            'font_family' => 'nullable|string|in:Inter,Roboto,Open Sans,Lato,Poppins,Montserrat',

            // Text overrides
            'text_available' => 'nullable|string|max:255',
            'text_transitioning' => 'nullable|string|max:255',
            'text_reserved' => 'nullable|string|max:255',
            'text_checkin' => 'nullable|string|max:255',

            // Linked displays
            'display_ids' => 'nullable|array',
            'display_ids.*' => [
                Rule::exists('displays', 'id')->where('workspace_id', $workspaceId),
            ],
        ];
    }
}
