<?php

namespace App\Http\Controllers;

use App\Enums\FunnelStep;
use App\Enums\OnboardingQuestion;
use App\Models\OnboardingAnswer;
use App\Models\Workspace;
use App\Services\Funnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The optional workspace questions on the dashboard.
 *
 * Every click is saved on its own, so someone who answers only the first question still
 * leaves that answer behind.
 */
class WorkspaceProfileController extends Controller
{
    /**
     * Where these answers come from, as stored in onboarding_answers.source.
     */
    private const SOURCE = 'dashboard_card';

    public function update(Request $request): Response|RedirectResponse
    {
        $workspace = $this->workspace($request);

        $question = OnboardingQuestion::tryFrom((string) $request->input('question'));
        abort_unless($question !== null, 422, 'Unknown question.');

        $options = array_column($question->options()::cases(), 'value');
        $withText = $question->optionWithText();

        $data = $request->validate([
            'value' => ['required', 'string', Rule::in([...$options, OnboardingAnswer::SKIPPED])],
            'text' => [$withText ? Rule::requiredIf($request->input('value') === $withText) : 'nullable', 'nullable', 'string', 'max:255'],
        ], [
            'text.required' => 'Enter a name first.',
        ]);

        $workspace->onboardingAnswers()->create([
            'user_id' => $request->user()->id,
            'question' => $question->value,
            'value' => $data['value'],
            'text' => $data['value'] === $withText ? $data['text'] : null,
            'source' => self::SOURCE,
            'answered_at' => now(),
        ]);

        return $this->done($request);
    }

    public function dismiss(Request $request): Response|RedirectResponse
    {
        $workspace = $this->workspace($request);

        Funnel::recordOnce(FunnelStep::PROFILE_CARD_DISMISSED, $workspace, $request->user());

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
