<?php

namespace App\Models;

use App\Enums\RoadmapStatus;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoadmapItem extends Model
{
    use HasUlid;

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
        'status' => RoadmapStatus::class,
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
        $when = collect(RoadmapStatus::cases())
            ->map(fn($s) => "WHEN '{$s->value}' THEN {$s->sortPriority()}")
            ->implode(' ');

        return $query->orderByRaw("CASE status {$when} END")
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }
}
