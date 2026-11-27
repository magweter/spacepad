<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer to one onboarding question. A changed answer is a new row, so the history stays.
 */
class OnboardingAnswer extends Model
{
    use HasUlid;

    /**
     * Stored as the value of a question that was shown and passed over.
     */
    public const SKIPPED = 'skipped';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'question',
        'value',
        'text',
        'source',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
