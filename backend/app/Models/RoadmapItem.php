<?php

namespace App\Models;

use App\Enums\RoadmapStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoadmapItem extends Model
{
    protected $fillable = [
        'title',
        'description',
        'status',
        'expected_at',
        'is_approved',
        'submitted_by_user_id',
        'sort_order',
    ];

    protected $casts = [
        'status'      => RoadmapStatus::class,
        'expected_at' => 'date',
        'is_approved' => 'boolean',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(RoadmapVote::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("
            CASE status
                WHEN 'building'    THEN 0
                WHEN 'planned'     THEN 1
                WHEN 'considering' THEN 2
                WHEN 'shipped'     THEN 3
            END
        ")->orderBy('sort_order')->orderBy('id');
    }
}
