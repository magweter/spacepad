<?php

namespace App\Http\Controllers;

use App\Enums\PreviousSolution;
use App\Enums\RoomsEstimate;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The two optional workspace questions on the dashboard.
 *
 * Every click is saved on its own, so someone who answers only the first question still
 * leaves that answer behind.
 */
class WorkspaceProfileController extends Controller
{
    public function update(Request $request): Response|RedirectResponse
    {
        $workspace = $this->workspace($request);

        $data = $request->validate([
            'rooms_estimate' => ['nullable', Rule::enum(RoomsEstimate::class)],
            'previous_solution' => ['nullable', Rule::enum(PreviousSolution::class)],
            'previous_solution_other' => ['nullable', 'required_if:previous_solution,other', 'string', 'max:255'],
        ]);

        $changes = array_filter($data, fn ($value) => $value !== null);

        if (isset($changes['previous_solution'])) {
            $changes['profile_answered_at'] = now();

            if ($changes['previous_solution'] !== PreviousSolution::OTHER->value) {
                $changes['previous_solution_other'] = null;
            }
        }

        $workspace->update($changes);

        return $this->done($request);
    }

    public function dismiss(Request $request): Response|RedirectResponse
    {
        $this->workspace($request)->update(['profile_dismissed_at' => now()]);

        return $this->done($request);
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = $request->user()->getSelectedWorkspace();

        abort_unless($workspace !== null, 404);
        $this->authorize('update', $workspace);

        return $workspace;
    }

    private function done(Request $request): Response|RedirectResponse
    {
        return $request->expectsJson() ? response()->noContent() : back();
    }
}
