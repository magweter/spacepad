<?php

namespace App\Http\Controllers;

use App\Enums\RoadmapStatus;
use App\Models\RoadmapItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminRoadmapController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->checkAccess();
            return $next($request);
        });
    }

    private function checkAccess(): void
    {
        $user = Auth::user();
        if (session()->get('impersonating') || !$user?->isAdmin() || config('settings.is_self_hosted')) {
            abort(403);
        }
    }

    public function index(): View
    {
        $items = RoadmapItem::withCount('votes')
            ->with('submittedBy')
            ->orderBy('is_approved')
            ->ordered()
            ->get();

        return view('pages.admin.roadmap.index', compact('items'));
    }

    public function create(): View
    {
        $statuses = RoadmapStatus::cases();
        $defaultStatus = RoadmapStatus::Considering->value;
        return view('pages.admin.roadmap.form', compact('statuses', 'defaultStatus'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'status'      => ['required', Rule::enum(RoadmapStatus::class)],
            'expected_at' => 'nullable|date',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        RoadmapItem::create([...$validated, 'is_approved' => true]);

        return redirect()->route('admin.roadmap.index')->with('success', 'Item created.');
    }

    public function edit(RoadmapItem $roadmapItem): View
    {
        $statuses = RoadmapStatus::cases();
        $defaultStatus = RoadmapStatus::Considering->value;
        return view('pages.admin.roadmap.form', ['item' => $roadmapItem, 'statuses' => $statuses, 'defaultStatus' => $defaultStatus]);
    }

    public function update(Request $request, RoadmapItem $roadmapItem): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'status'      => ['required', Rule::enum(RoadmapStatus::class)],
            'expected_at' => 'nullable|date',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $roadmapItem->update($validated);

        return redirect()->route('admin.roadmap.index')->with('success', 'Item updated.');
    }

    public function approve(RoadmapItem $roadmapItem): RedirectResponse
    {
        $roadmapItem->update(['is_approved' => true]);
        return back()->with('success', 'Suggestion approved and now visible.');
    }

    public function destroy(RoadmapItem $roadmapItem): RedirectResponse
    {
        $roadmapItem->delete();
        return back()->with('success', 'Item deleted.');
    }
}
