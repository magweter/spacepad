<?php

namespace App\Models;

use App\Enums\DisplayMode;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Panel extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'user_id',
        'name',
        'display_mode',
    ];

    protected $casts = [
        'display_mode' => DisplayMode::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function displays(): BelongsToMany
    {
        return $this->belongsToMany(Display::class, 'panel_displays')
            ->withPivot('order')
            ->orderBy('panel_displays.order');
    }

    public function getDisplays()
    {
        return $this->displays;
    }

    public function getDisplayMode(): DisplayMode
    {
        return $this->display_mode;
    }

    public function hasMultipleDisplays(): bool
    {
        return $this->displays()->count() > 1;
    }
}

