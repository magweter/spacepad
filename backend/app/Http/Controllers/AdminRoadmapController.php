<?php

namespace App\Http\Controllers;

use App\Enums\RoadmapStatus;
use App\Models\RoadmapItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminRoadmapController extends Controller
{
    private function checkAccess(): void
    {
        $user = Auth::user();
        if (session()->get('impersonating') || !$user?->isAdmin() || config('settings.is_self_hosted')) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->checkAccess();

        $items = RoadmapItem::withCount('votes')
            ->with('submittedBy')
            ->orderBy('is_approved')
            ->ordered()
            ->get();

        return view('pages.admin.roadmap.index', compact('items'));
    }

    public function create(): View
    {
        $this->checkAccess();
        $statuses = RoadmapStatus::cases();
        return view('pages.admin.roadmap.form', compact('statuses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAccess();

        $validated = $request->validate([
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'status'      => 'required|in:considering,planned,building,shipped',
            'expected_at' => 'nullable|date',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        RoadmapItem::create([...$validated, 'is_approved' => true]);

        return redirect()->route('admin.roadmap.index')->with('success', 'Item created.');
    }

    public function edit(RoadmapItem $roadmapItem): View
    {
        $this->checkAccess();
        $statuses = RoadmapStatus::cases();
        return view('pages.admin.roadmap.form', ['item' => $roadmapItem, 'statuses' => $statuses]);
    }

    public function update(Request $request, RoadmapItem $roadmapItem): RedirectResponse
    {
        $this->checkAccess();

        $validated = $request->validate([
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'status'      => 'required|in:considering,planned,building,shipped',
            'expected_at' => 'nullable|date',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $roadmapItem->update($validated);

        return redirect()->route('admin.roadmap.index')->with('success', 'Item updated.');
    }

    public function approve(RoadmapItem $roadmapItem): RedirectResponse
    {
        $this->checkAccess();
        $roadmapItem->update(['is_approved' => true]);
        return back()->with('success', 'Suggestion approved and now visible.');
    }

    public function destroy(RoadmapItem $roadmapItem): RedirectResponse
    {
        $this->checkAccess();
        $roadmapItem->delete();
        return back()->with('success', 'Item deleted.');
    }
}
