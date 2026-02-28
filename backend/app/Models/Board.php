<?php

namespace App\Models;

use App\Traits\HasUlid;
use App\Enums\DisplayStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Board extends Model
{
    use HasFactory;
    use HasUlid;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'title',
        'subtitle',
        'show_all_displays',
        'theme',
        'logo',
        'show_title',
        'show_booker',
        'show_next_event',
        'show_transitioning',
        'transitioning_minutes',
        'font_family',
        'language',
        'view_mode',
        'show_meeting_title',
    ];

    protected $casts = [
        'show_all_displays' => 'boolean',
        'show_title' => 'boolean',
        'show_booker' => 'boolean',
        'show_next_event' => 'boolean',
        'show_transitioning' => 'boolean',
        'transitioning_minutes' => 'integer',
        'show_meeting_title' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function displays(): BelongsToMany
    {
        return $this->belongsToMany(Display::class, 'board_displays')
            ->withTimestamps();
    }

    /**
     * Get the displays that should be shown on this board
     * If show_all_displays is true, returns all active displays from the workspace
     * Otherwise, returns only the selected displays from the pivot table
     */
    public function getDisplaysToShow()
    {
        if ($this->show_all_displays) {
            return Display::where('workspace_id', $this->workspace_id)
                ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
                ->with(['settings', 'user'])
                ->orderBy('name')
                ->get();
        }

        return $this->displays()
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->with(['settings', 'user'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if a display is included in this board
     */
    public function hasDisplay(Display $display): bool
    {
        if ($this->show_all_displays) {
            return $display->workspace_id === $this->workspace_id;
        }

        return $this->displays()->where('displays.id', $display->id)->exists();
    }

    /**
     * Get the count of displays shown on this board
     */
    public function getDisplayCountAttribute(): int
    {
        return $this->getDisplaysToShow()->count();
    }
}
