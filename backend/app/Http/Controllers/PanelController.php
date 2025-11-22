<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePanelRequest;
use App\Http\Requests\UpdatePanelRequest;
use App\Models\Panel;
use App\Services\PanelService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;

class PanelController extends Controller
{
    public function __construct(
        protected PanelService $panelService
    ) {
        $this->authorizeResource(Panel::class, 'panel');
    }

    public function index(): View|Factory|Application
    {
        $panels = Panel::where('user_id', auth()->id())
            ->withCount('displays')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pages.panels.index', [
            'panels' => $panels,
        ]);
    }

    public function create(): View|Factory|Application
    {
        $displays = \App\Models\Display::where('user_id', auth()->id())
            ->whereIn('status', [\App\Enums\DisplayStatus::READY, \App\Enums\DisplayStatus::ACTIVE])
            ->orderBy('name')
            ->get();

        return view('pages.panels.create', [
            'displays' => $displays,
        ]);
    }

    public function store(CreatePanelRequest $request): RedirectResponse
    {
        $panel = $this->panelService->createPanel($request->validated());

        return redirect()
            ->route('panels.index')
            ->with('success', 'Panel created successfully!');
    }

    public function show(Panel $panel): View|Factory|Application
    {
        $this->authorize('view', $panel);

        return view('pages.panels.show', [
            'panel' => $panel,
        ]);
    }

    public function edit(Panel $panel): View|Factory|Application
    {
        $this->authorize('update', $panel);

        $displays = \App\Models\Display::where('user_id', auth()->id())
            ->whereIn('status', [\App\Enums\DisplayStatus::READY, \App\Enums\DisplayStatus::ACTIVE])
            ->orderBy('name')
            ->get();

        $selectedDisplayIds = $panel->displays()->pluck('displays.id')->toArray();

        return view('pages.panels.edit', [
            'panel' => $panel,
            'displays' => $displays,
            'selectedDisplayIds' => $selectedDisplayIds,
        ]);
    }

    public function update(UpdatePanelRequest $request, Panel $panel): RedirectResponse
    {
        $this->authorize('update', $panel);

        $this->panelService->updatePanel($panel, $request->validated());

        return redirect()
            ->route('panels.index')
            ->with('success', 'Panel updated successfully!');
    }

    public function destroy(Panel $panel): RedirectResponse
    {
        $this->authorize('delete', $panel);

        $panel->delete();

        return redirect()
            ->route('panels.index')
            ->with('success', 'Panel deleted successfully!');
    }
}

