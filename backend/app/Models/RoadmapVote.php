<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadmapVote extends Model
{
    use HasUlid;

    public const UPDATED_AT = null;

    protected $fillable = ['roadmap_item_id', 'user_id'];

    public function roadmapItem(): BelongsTo
    {
        return $this->belongsTo(RoadmapItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
