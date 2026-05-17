<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeatureFlag extends Model
{
    use HasUlid;

    protected $fillable = [
        'user_id',
        'advertisement',
    ];

    protected $casts = [
        'advertisement' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
