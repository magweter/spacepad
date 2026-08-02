<?php

namespace App\Models;

use App\Enums\DisplayStatus;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

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
        'categories',
        'show_meeting_title',
        'show_join_button',
        'is_public',
        'public_token',
    ];

    protected $casts = [
        'show_all_displays' => 'boolean',
        'show_title' => 'boolean',
        'show_booker' => 'boolean',
        'show_next_event' => 'boolean',
        'show_transitioning' => 'boolean',
        'transitioning_minutes' => 'integer',
        'categories' => 'array',
        'show_meeting_title' => 'boolean',
        'show_join_button' => 'boolean',
        'is_public' => 'boolean',
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
            ->using(BoardDisplay::class)
            ->withTimestamps();
    }

    /**
     * Get the query builder for displays that should be shown on this board
     * If show_all_displays is true, returns query for all active displays from the workspace
     * Otherwise, returns query for selected displays from the pivot table
     */
    public function getDisplaysToShowQuery()
    {
        if ($this->show_all_displays) {
            return Display::where('workspace_id', $this->workspace_id)
                ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE]);
        }

        return $this->displays()
            ->where('workspace_id', $this->workspace_id)
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE]);
    }

    /**
     * Get the displays that should be shown on this board
     * If show_all_displays is true, returns all active displays from the workspace
     * Otherwise, returns only the selected displays from the pivot table
     */
    public function getDisplaysToShow()
    {
        return $this->getDisplaysToShowQuery()
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

        return $this->displays()
            ->where('workspace_id', $this->workspace_id)
            ->where('displays.id', $display->id)
            ->exists();
    }

    /**
     * Get the count of displays shown on this board
     */
    public function getDisplayCountAttribute(): int
    {
        return $this->getDisplaysToShowQuery()->count();
    }

    /**
     * Group display status rows into this board's configured categories.
     *
     * Returns an ordered collection of ['name' => ?string, 'displays' => Collection]. Category
     * order and the order of displays within a category follow the stored `categories` JSON, so
     * the array order is what the board renders. A null name is the catch-all group for displays
     * that are not in any category; it is always last.
     *
     * Categories that end up empty are skipped (an empty section header on the board is noise),
     * and display ids that no longer resolve to a shown display are ignored — this is what keeps
     * the JSON safe when a display is deleted or removed from the board.
     *
     * @param  Collection<int, array{display: Display}>  $displayData
     * @return Collection<int, array{name: string|null, displays: Collection}>
     */
    public function groupDisplayData(Collection $displayData): Collection
    {
        $categories = $this->categories ?? [];

        if (empty($categories)) {
            return collect([['name' => null, 'displays' => $displayData]]);
        }

        $byId = $displayData->keyBy(fn ($row) => $row['display']->id);
        $groups = collect();
        $assigned = [];

        foreach ($categories as $category) {
            $name = trim((string) ($category['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $displays = collect();

            foreach ($category['display_ids'] ?? [] as $displayId) {
                if (isset($assigned[$displayId]) || ! $byId->has($displayId)) {
                    continue;
                }

                $assigned[$displayId] = true;
                $displays->push($byId->get($displayId));
            }

            if ($displays->isNotEmpty()) {
                $groups->push(['name' => $name, 'displays' => $displays]);
            }
        }

        $ungrouped = $displayData
            ->reject(fn ($row) => isset($assigned[$row['display']->id]))
            ->values();

        if ($ungrouped->isNotEmpty()) {
            $groups->push(['name' => null, 'displays' => $ungrouped]);
        }

        return $groups;
    }
}
