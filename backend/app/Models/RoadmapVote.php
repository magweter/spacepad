<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadmapVote extends Model
{
    public $timestamps = false;

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
