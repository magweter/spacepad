<?php

namespace App\Http\Controllers;

use App\Enums\RoadmapStatus;
use App\Http\Controllers\Concerns\ChecksAdminAccess;
use App\Models\RoadmapItem;
use App\Support\AdminPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminRoadmapController extends Controller
{
    use ChecksAdminAccess;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->checkAdminAccess();

            return $next($request);
        });
    }

    public function index(Request $request): Response
    {
        $items = RoadmapItem::withCount('votes')
            ->with('submittedBy')
            ->orderBy('is_approved')
            ->ordered()
            ->get();

        $pending = $items->where('is_approved', false)->values();
        $approved = $items->where('is_approved', true)->values();

        return Inertia::render('Admin/Roadmap', [
            ...AdminPage::props($request),
            'pending' => $pending->map(fn (RoadmapItem $item) => [
                ...$this->row($item, 100),
                'submittedBy' => $item->submittedBy ? ['name' => $item->submittedBy->name, 'email' => $item->submittedBy->email] : null,
                'date' => $item->created_at?->format('d M Y'),
                'approveUrl' => route('admin.roadmap.approve', $item),
            ])->all(),
            'approved' => $approved->map(fn (RoadmapItem $item) => [
                ...$this->row($item, 80),
                'status' => ['value' => $item->status->value, 'label' => $item->status->label()],
                'expected' => $item->expected_at?->format('M Y'),
                'votes' => (int) $item->votes_count,
            ])->all(),
            'urls' => ['create' => route('admin.roadmap.create')],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'status' => ['required', Rule::enum(RoadmapStatus::class)],
            'expected_at' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        RoadmapItem::create([...$validated, 'is_approved' => true]);

        return redirect()->route('admin.roadmap.index')->with('success', 'Item created.');
    }

    public function edit(Request $request, RoadmapItem $roadmapItem): Response
    {
        return $this->form($request, $roadmapItem);
    }

    public function update(Request $request, RoadmapItem $roadmapItem): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'status' => ['required', Rule::enum(RoadmapStatus::class)],
            'expected_at' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0',
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

    /**
     * What both tables show of an item, and the links its actions need.
     *
     * @return array<string, mixed>
     */
    private function row(RoadmapItem $item, int $descriptionLimit): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description ? Str::limit($item->description, $descriptionLimit) : null,
            'editUrl' => route('admin.roadmap.edit', $item),
            'deleteUrl' => route('admin.roadmap.destroy', $item),
        ];
    }

    private function form(Request $request, ?RoadmapItem $item): Response
    {
        return Inertia::render('Admin/RoadmapForm', [
            ...AdminPage::props($request),
            'item' => $item ? [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description ?? '',
                'status' => $item->status->value,
                'expected_at' => $item->expected_at?->format('Y-m-d') ?? '',
                'sort_order' => $item->sort_order ?? 0,
            ] : null,
            'statuses' => collect(RoadmapStatus::cases())
                ->map(fn (RoadmapStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
            'defaultStatus' => RoadmapStatus::Considering->value,
            'urls' => [
                'index' => route('admin.roadmap.index'),
                'submit' => $item ? route('admin.roadmap.update', $item) : route('admin.roadmap.store'),
            ],
        ]);
    }
}
