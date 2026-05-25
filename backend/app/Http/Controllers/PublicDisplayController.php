<?php

namespace App\Http\Controllers;

use App\Enums\DisplayStatus;
use App\Helpers\DisplaySettings;
use App\Models\Display;
use App\Models\User;
use App\Services\EventService;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class PublicDisplayController extends Controller
{
    public function __construct(
        protected EventService $eventService,
        protected ImageService $imageService,
    ) {
    }

    public function show(string $token): View
    {
        $display = Display::where('display_token', $token)
            ->where('status', '!=', DisplayStatus::DEACTIVATED)
            ->with('settings', 'calendar')
            ->firstOrFail();

        $events = $this->eventService->getEventsForDisplay($display->id);

        $now = now();
        $currentEvent = $events->first(fn ($e) => $e->start <= $now && $e->end > $now);
        $nextEvent = $events->filter(fn ($e) => $e->start > $now)->first();
        $minutesUntilNext = $nextEvent ? $now->diffInMinutes($nextEvent->start, false) : null;
        $isTransitioning = !$currentEvent
            && $minutesUntilNext !== null
            && $minutesUntilNext >= 0
            && $minutesUntilNext <= 15;

        if ($currentEvent) {
            $roomStatus = 'reserved';
        } elseif ($isTransitioning) {
            $roomStatus = 'transitioning';
        } else {
            $roomStatus = 'available';
        }

        $backgroundUrl = $this->resolveBackgroundUrl($display, $token);
        $logoUrl = $this->resolveLogoUrl($display, $token);

        return view('pages.displays.public', compact(
            'display', 'events', 'roomStatus', 'currentEvent', 'nextEvent',
            'backgroundUrl', 'logoUrl', 'token'
        ));
    }

    public function image(string $token, string $type)
    {
        $display = Display::where('display_token', $token)
            ->with('settings')
            ->firstOrFail();

        return $this->imageService->serveImage($display, $type);
    }

    public function book(Request $request, string $token): JsonResponse
    {
        $display = Display::where('display_token', $token)
            ->where('status', '!=', DisplayStatus::DEACTIVATED)
            ->with('settings', 'calendar.outlookAccount', 'calendar.googleAccount', 'calendar.caldavAccount', 'calendar.room')
            ->firstOrFail();

        if (!$display->isBookingEnabled()) {
            return response()->json(['success' => false, 'message' => 'Booking is not enabled for this display.'], 403);
        }

        $validated = $request->validate([
            'duration' => 'required|integer|min:1|max:480',
            'summary' => 'nullable|string|max:255',
        ]);

        try {
            $this->eventService->bookRoom(
                displayId: $display->id,
                userId: $display->user_id,
                summary: trim($validated['summary'] ?? '') ?: 'Reserved',
                duration: (int) $validated['duration'],
            );

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            logger()->warning('Public display booking failed', [
                'display_id' => $display->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Unable to book room right now.'], 400);
        }
    }

    private function resolveBackgroundUrl(Display $display, string $token): ?string
    {
        $background = DisplaySettings::getBackgroundImage($display);
        if (!$background) {
            return null;
        }

        if (isset(ImageService::DEFAULT_BACKGROUNDS[$background])) {
            return asset(ImageService::DEFAULT_BACKGROUNDS[$background]);
        }

        return route('displays.public.image', [$token, 'background']);
    }

    private function resolveLogoUrl(Display $display, string $token): ?string
    {
        $logo = DisplaySettings::getLogo($display);
        if (!$logo) {
            return null;
        }

        return route('displays.public.image', [$token, 'logo']);
    }

    public function connectForm(): View
    {
        return view('pages.displays.connect');
    }

    public function connectLookup(Request $request): View|RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|max:20',
        ]);

        // Strip whitespace so "123 456" and "123456" both work
        $code = preg_replace('/\s+/', '', $request->input('code'));

        // Read the code without consuming it (display web setup does not invalidate the pairing code)
        $userId = cache()->get("connect-code:$code");

        if (!$userId) {
            return back()->withErrors(['code' => 'Invalid or expired connect code. Check the code shown on your management dashboard.'])->withInput();
        }

        $user = User::find($userId);
        if (!$user) {
            return back()->withErrors(['code' => 'Invalid connect code.'])->withInput();
        }

        $workspace = $user->getSelectedWorkspace() ?? $user->primaryWorkspace();
        if (!$workspace) {
            return back()->withErrors(['code' => 'No workspace found for this account.'])->withInput();
        }

        $displays = Display::where('workspace_id', $workspace->id)
            ->whereNotNull('display_token')
            ->where('status', '!=', DisplayStatus::DEACTIVATED)
            ->with('settings')
            ->get();

        return view('pages.displays.connect-select', [
            'displays' => $displays,
            'workspaceName' => $workspace->name,
        ]);
    }
}
