<?php

namespace App\Http\Controllers;

use App\Enums\DisplayStatus;
use App\Enums\EventStatus;
use App\Helpers\DisplaySettings;
use App\Http\Requests\CreateBoardRequest;
use App\Http\Requests\UpdateBoardRequest;
use App\Models\Display;
use App\Models\Board;
use App\Services\EventService;
use App\Services\ImageService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

class BoardController extends Controller
{
    public function __construct(
        protected EventService $eventService,
        protected ImageService $imageService
    ) {
    }

    /**
     * Display a listing of boards for the current workspace
     */
    public function index(): View|Factory|Application
    {
        $user = auth()->user();
        
        // Check Pro access
        if (!$user->hasProForCurrentWorkspace()) {
            abort(403, 'Boards is a Pro feature. Please upgrade to access this feature.');
        }
        
        $selectedWorkspace = $user->getSelectedWorkspace();
        
        if (!$selectedWorkspace) {
            abort(404, 'No workspace found');
        }
        
        $boards = Board::where('workspace_id', $selectedWorkspace->id)
            ->with(['user', 'displays'])
            ->orderBy('name')
            ->get();
        
        return view('pages.boards.index', [
            'boards' => $boards,
            'workspace' => $selectedWorkspace,
        ]);
    }

    /**
     * Show the form for creating a new board
     */
    public function create(): View|Factory|Application
    {
        $user = auth()->user();
        
        // Check Pro access
        if (!$user->hasProForCurrentWorkspace()) {
            abort(403, 'Boards is a Pro feature. Please upgrade to access this feature.');
        }
        
        $this->authorize('create', Board::class);
        
        $selectedWorkspace = $user->getSelectedWorkspace();
        
        if (!$selectedWorkspace) {
            abort(404, 'No workspace found');
        }
        
        // Get all active displays from the workspace
        $displays = Display::where('workspace_id', $selectedWorkspace->id)
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->orderBy('name')
            ->get();
        
        return view('pages.boards.form', [
            'board' => null,
            'displays' => $displays,
            'workspace' => $selectedWorkspace,
        ]);
    }

    /**
     * Store a newly created board
     */
    public function store(CreateBoardRequest $request): RedirectResponse
    {
        $user = auth()->user();
        
        // Check Pro access
        if (!$user->hasProForCurrentWorkspace()) {
            return redirect()->back()->with('error', 'Boards is a Pro feature. Please upgrade to access this feature.');
        }
        
        $this->authorize('create', Board::class);
        
        $validated = $request->validated();
        $selectedWorkspace = $user->getSelectedWorkspace();
        
        if (!$selectedWorkspace || $selectedWorkspace->id !== $validated['workspace_id']) {
            return redirect()->back()->with('error', 'Invalid workspace selected.');
        }
        
        // Verify user has access to this workspace
        if (!$selectedWorkspace->hasMember($user)) {
            return redirect()->back()->with('error', 'You do not have access to this workspace.');
        }
        
        // Create the board first
        $board = Board::create([
            'workspace_id' => $validated['workspace_id'],
            'user_id' => $user->id,
            'name' => $validated['name'],
            'title' => $validated['title'] ?? null,
            'subtitle' => $validated['subtitle'] ?? null,
            'show_all_displays' => $validated['show_all_displays'],
            'theme' => $validated['theme'] ?? 'dark',
            'show_title' => $validated['show_title'] ?? true,
            'show_booker' => $validated['show_booker'] ?? true,
            'show_next_event' => $validated['show_next_event'] ?? true,
            'show_transitioning' => $validated['show_transitioning'] ?? true,
            'transitioning_minutes' => $validated['transitioning_minutes'] ?? 10,
            'font_family' => $validated['font_family'] ?? 'Inter',
            'language' => $validated['language'] ?? 'en',
            'show_meeting_title' => $validated['show_meeting_title'] ?? true,
        ]);
        
        // Handle logo upload after board is created
        if ($request->hasFile('logo')) {
            $logoPath = $this->imageService->storeBoardLogoFile($request->file('logo'), $board);
            $board->update(['logo' => $logoPath]);
        }
        
        // Sync displays if not showing all
        if (!$validated['show_all_displays']) {
            if (isset($validated['display_ids']) && is_array($validated['display_ids']) && count($validated['display_ids']) > 0) {
                // Verify all display IDs belong to the workspace
                $displayIds = Display::where('workspace_id', $selectedWorkspace->id)
                    ->whereIn('id', $validated['display_ids'])
                    ->pluck('id')
                    ->toArray();
                
                $board->displays()->sync($displayIds);
            } else {
                // No displays selected, clear associations
                $board->displays()->detach();
            }
        } else {
            // Clear all display associations if showing all
            $board->displays()->detach();
        }
        
        return redirect(route('dashboard') . '?tab=boards')
            ->with('success', 'Board created successfully.');
    }

    /**
     * Display the specified board (the actual board view)
     */
    public function show(Board $board): View|Factory|Application
    {
        $user = auth()->user();
        
        // Check Pro access
        if (!$user->hasProForCurrentWorkspace()) {
            abort(403, 'Boards is a Pro feature. Please upgrade to access this feature.');
        }
        
        $this->authorize('view', $board);
        
        // Store board in a way that getDisplayStatusData can access it
        $this->currentBoard = $board;
        
        // Get displays to show
        $displays = $board->getDisplaysToShow();
        
        // Fetch events and determine status for each display
        $displayData = $this->getDisplayStatusData($displays, $board);
        
        return view('pages.boards.show', [
            'board' => $board,
            'displays' => $displayData,
            'workspace' => $board->workspace,
        ]);
    }
    
    private function getTransitioningMinutes($currentEvent, $nextEvent, ?Board $board = null): ?int
    {
        $transitioningMinutes = $board ? ($board->transitioning_minutes ?? 10) : 10;
        $now = now();
        
        // If current event is ending soon
        if ($currentEvent) {
            $minutesLeft = $currentEvent->end->diffInMinutes($now, false);
            if ($minutesLeft < $transitioningMinutes && $minutesLeft > 0) {
                return $minutesLeft;
            }
        }
        
        // If next event is starting soon
        if ($nextEvent) {
            $minutesUntil = $now->diffInMinutes($nextEvent->start, false);
            if ($minutesUntil < $transitioningMinutes && $minutesUntil > 0) {
                return $minutesUntil;
            }
        }
        
        return null;
    }

    /**
     * Show the form for editing the specified board
     */
    public function edit(Board $board): View|Factory|Application
    {
        $user = auth()->user();
        
        // Check Pro access
        if (!$user->hasProForCurrentWorkspace()) {
            abort(403, 'Boards is a Pro feature. Please upgrade to access this feature.');
        }
        
        $this->authorize('update', $board);
        
        // Get all active displays from the workspace
        $displays = Display::where('workspace_id', $board->workspace_id)
            ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
            ->orderBy('name')
            ->get();
        
        return view('pages.boards.form', [
            'board' => $board,
            'displays' => $displays,
            'workspace' => $board->workspace,
        ]);
    }

    /**
     * Update the specified board
     */
    public function update(UpdateBoardRequest $request, Board $board): RedirectResponse
    {
        $user = auth()->user();
        
        // Check Pro access
        if (!$user->hasProForCurrentWorkspace()) {
            return redirect()->back()->with('error', 'Boards is a Pro feature. Please upgrade to access this feature.');
        }
        
        $this->authorize('update', $board);
        
        $validated = $request->validated();
        
        // Verify workspace matches
        if ($board->workspace_id !== $validated['workspace_id']) {
            return redirect()->back()->with('error', 'Invalid workspace selected.');
        }
        
        // Handle logo upload/removal
        $logoPath = $board->logo;
        if ($request->boolean('remove_logo')) {
            $this->imageService->removeBoardLogoFile($board);
            $logoPath = null;
        } elseif ($request->hasFile('logo')) {
            // Remove old logo if exists
            $this->imageService->removeBoardLogoFile($board);
            // Store new logo
            $logoPath = $this->imageService->storeBoardLogoFile($request->file('logo'), $board);
        }
        
        // Update the board
        $board->update([
            'name' => $validated['name'],
            'title' => $validated['title'] ?? null,
            'subtitle' => $validated['subtitle'] ?? null,
            'show_all_displays' => $validated['show_all_displays'],
            'theme' => $validated['theme'] ?? 'dark',
            'logo' => $logoPath,
            'show_title' => $validated['show_title'] ?? true,
            'show_booker' => $validated['show_booker'] ?? true,
            'show_next_event' => $validated['show_next_event'] ?? true,
            'show_transitioning' => $validated['show_transitioning'] ?? true,
            'transitioning_minutes' => $validated['transitioning_minutes'] ?? 10,
            'font_family' => $validated['font_family'] ?? 'Inter',
            'language' => $validated['language'] ?? 'en',
            'show_meeting_title' => $validated['show_meeting_title'] ?? true,
        ]);
        
        // Sync displays if not showing all
        if (!$validated['show_all_displays']) {
            if (isset($validated['display_ids']) && is_array($validated['display_ids']) && count($validated['display_ids']) > 0) {
                // Verify all display IDs belong to the workspace
                $displayIds = Display::where('workspace_id', $board->workspace_id)
                    ->whereIn('id', $validated['display_ids'])
                    ->pluck('id')
                    ->toArray();
                
                $board->displays()->sync($displayIds);
            } else {
                // No displays selected, clear associations
                $board->displays()->detach();
            }
        } else {
            // Clear all display associations if showing all
            $board->displays()->detach();
        }
        
        return redirect(route('dashboard') . '?tab=boards')
            ->with('success', 'Board updated successfully.');
    }

    /**
     * Remove the specified board
     */
    public function destroy(Board $board): RedirectResponse
    {
        $user = auth()->user();
        
        // Check Pro access
        if (!$user->hasProForCurrentWorkspace()) {
            return redirect()->back()->with('error', 'Boards is a Pro feature. Please upgrade to access this feature.');
        }
        
        $this->authorize('delete', $board);
        
        // Remove logo file if exists
        $this->imageService->removeBoardLogoFile($board);
        
        $board->delete();
        
        return redirect(route('dashboard') . '?tab=boards')
            ->with('success', 'Board deleted successfully.');
    }

    /**
     * Serve board logo image
     */
    public function serveLogo(Board $board)
    {
        $this->authorize('view', $board);
        return $this->imageService->serveBoardLogo($board);
    }

    /**
     * Get display status data for a collection of displays
     * Extracted from FlightboardController for reusability
     */
    private function getDisplayStatusData(Collection $displays, ?Board $board = null): Collection
    {
        return $displays->map(function ($display) use ($board) {
            try {
                $events = $this->eventService->getEventsForDisplay($display->id)
                    ->where('status', '!=', EventStatus::CANCELLED);
                
                $now = now();
                $currentEvent = $events->first(function ($event) use ($now) {
                    return $event->start <= $now && $event->end > $now;
                });
                
                $upcomingEvents = $events->filter(function ($event) use ($now) {
                    return $event->start > $now;
                })->sortBy('start');
                
                $nextEvent = $upcomingEvents->first();
                
                // Get board settings
                $showTransitioning = $board ? ($board->show_transitioning ?? true) : true;
                
                // Get board language for translations
                $boardLanguage = $board ? ($board->language ?? 'en') : 'en';
                
                // Determine status
                $status = 'available'; // green
                $statusText = Lang::get('boards.available', [], $boardLanguage);
                
                if ($currentEvent) {
                    $status = 'busy'; // red
                    $statusText = Lang::get('boards.busy', [], $boardLanguage);
                } elseif ($showTransitioning && $this->isTransitioning($display, $currentEvent, $nextEvent, $board)) {
                    $status = 'transitioning'; // amber
                    $statusText = Lang::get('boards.transitioning', [], $boardLanguage);
                }
                
                // Check for check-in active
                $checkInEnabled = DisplaySettings::isCheckInEnabled($display);
                $checkInEvent = null;
                if ($checkInEnabled) {
                    $checkInMinutes = DisplaySettings::getCheckInMinutes($display);
                    $checkInGracePeriod = DisplaySettings::getCheckInGracePeriod($display);
                    
                    $checkInEvent = $events->first(function ($event) use ($now, $checkInMinutes, $checkInGracePeriod) {
                        if (!$event->checkInRequired()) {
                            return false;
                        }
                        $windowStart = $event->start->copy()->subMinutes($checkInMinutes);
                        $windowEnd = $event->start->copy()->addMinutes($checkInGracePeriod);
                        return $now->isAfter($windowStart) && $now->isBefore($windowEnd);
                    });
                    
                    if ($checkInEvent) {
                        $status = 'transitioning';
                        $statusText = Lang::get('boards.check_in', [], $boardLanguage);
                    }
                }
                
                // Get board settings for meeting title privacy
                $showMeetingTitle = $board ? ($board->show_meeting_title ?? true) : DisplaySettings::getShowMeetingTitle($display);
                
                return [
                    'display' => $display,
                    'status' => $status,
                    'statusText' => $statusText,
                    'currentEvent' => $currentEvent ? [
                        'summary' => $showMeetingTitle 
                            ? $currentEvent->summary 
                            : (DisplaySettings::getReservedText($display) ?? 'Reserved'),
                        'start' => $currentEvent->start,
                        'end' => $currentEvent->end,
                        'organizer' => $currentEvent->user?->name ?? 'Unknown',
                    ] : null,
                    'nextEvent' => $nextEvent ? [
                        'summary' => $showMeetingTitle 
                            ? $nextEvent->summary 
                            : (DisplaySettings::getReservedText($display) ?? 'Reserved'),
                        'start' => $nextEvent->start,
                        'end' => $nextEvent->end,
                        'organizer' => $nextEvent->user?->name ?? 'Unknown',
                    ] : null,
                    'transitioningMinutes' => $this->getTransitioningMinutes($currentEvent, $nextEvent, $board),
                ];
            } catch (\Exception $e) {
                logger()->error('Failed to fetch events for display in board', [
                    'display_id' => $display->id,
                    'error' => $e->getMessage(),
                ]);
                
                // Get board language for translations
                $boardLanguage = $board ? ($board->language ?? 'en') : 'en';
                
                return [
                    'display' => $display,
                    'status' => 'error',
                    'statusText' => Lang::get('boards.error', [], $boardLanguage),
                    'currentEvent' => null,
                    'nextEvent' => null,
                ];
            }
        });
    }
    
    /**
     * Check if display is in transitioning state
     */
    private function isTransitioning($display, $currentEvent, $nextEvent, ?Board $board = null): bool
    {
        $checkInEnabled = DisplaySettings::isCheckInEnabled($display);
        if ($checkInEnabled) {
            return false; // Check-in logic handled separately
        }
        
        $transitioningMinutes = $board ? ($board->transitioning_minutes ?? 10) : 10;
        $now = now();
        
        // Current event ending within configured minutes
        if ($currentEvent) {
            $minutesLeft = $currentEvent->end->diffInMinutes($now, false);
            if ($minutesLeft < $transitioningMinutes && $minutesLeft > 0) {
                return true;
            }
        }
        
        // Next event starting within configured minutes
        if ($nextEvent) {
            $minutesUntil = $now->diffInMinutes($nextEvent->start, false);
            if ($minutesUntil < $transitioningMinutes && $minutesUntil > 0) {
                return true;
            }
        }
        
        return false;
    }
}
