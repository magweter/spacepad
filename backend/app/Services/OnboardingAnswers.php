<?php

namespace App\Services;

use App\Enums\OnboardingQuestion;
use App\Models\OnboardingAnswer;
use App\Models\User;
use App\Models\Workspace;

/**
 * Writes answers to onboarding questions, wherever they are asked.
 */
class OnboardingAnswers
{
    /**
     * Store one answer. Null, an empty string or an empty list is stored as skipped, so a
     * question that was shown and passed over can be told apart from one never asked.
     *
     * @param  string|array<int, string>|null  $value
     */
    public static function store(Workspace $workspace, User $user, OnboardingQuestion $question, string|array|null $value, ?string $text, string $source): void
    {
        $values = array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== ''));
        $values = $values ?: [OnboardingAnswer::SKIPPED];
        $now = now();

        foreach ($values as $option) {
            $workspace->onboardingAnswers()->create([
                'user_id' => $user->id,
                'question' => $question->value,
                'value' => $option,
                'text' => $option === $question->optionWithText() ? $text : null,
                'source' => $source,
                'answered_at' => $now,
            ]);
        }
    }

    /**
     * The latest answer to a question, or every option of the latest answer for a question
     * that takes several.
     *
     * @return string|array<int, string>|null
     */
    public static function latest(Workspace $workspace, OnboardingQuestion $question): string|array|null
    {
        $latest = $workspace->latestAnswer($question);

        if (! $latest || $latest->value === OnboardingAnswer::SKIPPED) {
            return $question->isMultiple() ? [] : null;
        }

        if (! $question->isMultiple()) {
            return $latest->value;
        }

        return $workspace->onboardingAnswers()
            ->where('question', $question->value)
            ->where('answered_at', $latest->answered_at)
            ->pluck('value')
            ->all();
    }

    /**
     * The options of a question in the shape the onboarding page renders.
     *
     * @return array<int, array{value: string, label: string, description: string|null, tag: string|null}>
     */
    public static function options(OnboardingQuestion $question): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => method_exists($case, 'description') ? $case->description() : null,
            'tag' => method_exists($case, 'tag') ? $case->tag() : null,
        ], array_values(array_filter(
            $question->options()::cases(),
            // A case can stay for earlier answers without being offered any more.
            fn ($case) => ! method_exists($case, 'offered') || $case->offered(),
        )));
    }
}
