<?php

namespace App\Enums;

/**
 * Why a workspace cancelled, as answered when it did.
 */
enum CancellationReason: string
{
    case TOO_EXPENSIVE = 'too_expensive';
    case MISSING_FEATURE = 'missing_feature';
    case DIDNT_WORK = 'didnt_work';
    case SWITCHED = 'switched';
    case PROJECT_ENDED = 'project_ended';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TOO_EXPENSIVE => 'Too expensive',
            self::MISSING_FEATURE => 'Missing a feature',
            self::DIDNT_WORK => "Didn't work well",
            self::SWITCHED => 'Switched to another product',
            self::PROJECT_ENDED => 'Project ended',
            self::OTHER => 'Other',
        };
    }

    /**
     * The follow-up question for reasons that are only useful with a name attached.
     */
    public function detailsPrompt(): ?string
    {
        return match ($this) {
            self::MISSING_FEATURE => 'Which feature?',
            self::SWITCHED => 'Which product?',
            self::OTHER => 'Tell us more',
            default => null,
        };
    }
}
