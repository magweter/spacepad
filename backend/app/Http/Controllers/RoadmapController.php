<?php

namespace App\Http\Controllers;

use App\Models\RoadmapItem;
use App\Models\RoadmapVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RoadmapController extends Controller
{
    public function vote(RoadmapItem $roadmapItem): JsonResponse
    {
        abort_if(config('settings.is_self_hosted'), 404);
        abort_unless($roadmapItem->is_approved, 404);

        $userId = auth()->id();
        $existing = RoadmapVote::where('roadmap_item_id', $roadmapItem->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $voted = false;
        } else {
            RoadmapVote::create(['roadmap_item_id' => $roadmapItem->id, 'user_id' => $userId]);
            $voted = true;
        }

        $votesCount = RoadmapVote::where('roadmap_item_id', $roadmapItem->id)->count();

        return response()->json(['voted' => $voted, 'votes_count' => $votesCount]);
    }

    public function suggest(Request $request): RedirectResponse
    {
        abort_if(config('settings.is_self_hosted'), 404);

        $validated = $request->validate([
            'suggestion_title' => 'required|string|min:5|max:150',
            'suggestion_description' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();

        RoadmapItem::create([
            'title' => $validated['suggestion_title'],
            'description' => $validated['suggestion_description'] ?? null,
            'status' => 'considering',
            'is_approved' => false,
            'submitted_by_user_id' => $user->id,
        ]);

        try {
            $body = "New feature request from {$user->name} ({$user->email}):\n\n"
                  ."Title: {$validated['suggestion_title']}\n"
                  .($validated['suggestion_description'] ? "Details: {$validated['suggestion_description']}" : '');

            Mail::raw($body, fn ($mail) => $mail
                ->to('support@spacepad.io')
                ->subject("Spacepad request — {$validated['suggestion_title']}")
                ->replyTo($user->email, $user->name)
            );
        } catch (\Exception $e) {
            Log::error('Failed to send suggestion email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('suggestion_sent', true);
    }
}
