<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisplayProfile extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(DisplayProfileSetting::class);
    }

    public function displays(): HasMany
    {
        return $this->hasMany(Display::class);
    }
}
