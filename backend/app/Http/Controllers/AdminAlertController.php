<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksAdminAccess;
use App\Models\PortalAlert;
use App\Support\AdminPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin: alerts set by hand, such as a calendar provider outage, shown at the top of the portal.
 * Every change clears the cached list, so the portal shows it on the next page load.
 */
class AdminAlertController extends Controller
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
        $alerts = PortalAlert::orderByDesc('created_at')->get()->groupBy(fn (PortalAlert $alert) => $alert->status());

        return Inertia::render('Admin/Alerts', [
            ...AdminPage::props($request),
            'alerts' => [
                'active' => ($alerts['active'] ?? collect())->map(fn ($a) => $this->row($a))->values()->all(),
                'scheduled' => ($alerts['scheduled'] ?? collect())->sortBy('starts_at')->map(fn ($a) => $this->row($a))->values()->all(),
                'ended' => ($alerts['ended'] ?? collect())->sortByDesc('ends_at')->take(20)->map(fn ($a) => $this->row($a))->values()->all(),
            ],
            'urls' => ['create' => route('admin.alerts.create')],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function store(Request $request): RedirectResponse
    {
        PortalAlert::create([...$this->validated($request), 'created_by' => $request->user()->id]);
        PortalAlert::forgetCache();

        return redirect()->route('admin.alerts.index')->with('success', 'Alert saved.');
    }

    public function edit(Request $request, PortalAlert $portalAlert): Response
    {
        return $this->form($request, $portalAlert);
    }

    public function update(Request $request, PortalAlert $portalAlert): RedirectResponse
    {
        $portalAlert->update($this->validated($request));
        PortalAlert::forgetCache();

        return redirect()->route('admin.alerts.index')->with('success', 'Alert saved.');
    }

    public function end(PortalAlert $portalAlert): RedirectResponse
    {
        $portalAlert->update(['ends_at' => now()]);
        PortalAlert::forgetCache();

        return redirect()->route('admin.alerts.index')->with('success', 'Alert ended. The portal no longer shows it.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:500'],
            'tone' => ['required', Rule::in(PortalAlert::TONES)],
            'provider' => ['nullable', Rule::in(PortalAlert::PROVIDERS)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', $request->filled('starts_at') ? 'after:starts_at' : 'after:now'],
        ], [
            'ends_at.after' => 'The end has to be after the start.',
        ]);

        return [
            ...$data,
            'body' => $data['body'] ?? null,
            'provider' => $data['provider'] ?? null,
            'starts_at' => isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null,
            'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PortalAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'title' => $alert->title,
            'body' => $alert->body,
            'tone' => $alert->tone,
            'provider' => $alert->provider,
            'startsAt' => $alert->starts_at?->format('Y-m-d H:i'),
            'endsAt' => $alert->ends_at?->format('Y-m-d H:i'),
            'status' => $alert->status(),
            'editUrl' => route('admin.alerts.edit', $alert),
            'endUrl' => route('admin.alerts.end', $alert),
        ];
    }

    private function form(Request $request, ?PortalAlert $alert): Response
    {
        return Inertia::render('Admin/AlertForm', [
            ...AdminPage::props($request),
            'alert' => $alert ? [
                'id' => $alert->id,
                'title' => $alert->title,
                'body' => $alert->body ?? '',
                'tone' => $alert->tone,
                'provider' => $alert->provider ?? '',
                'starts_at' => $alert->starts_at?->format('Y-m-d\TH:i') ?? '',
                'ends_at' => $alert->ends_at?->format('Y-m-d\TH:i') ?? '',
            ] : null,
            'urls' => [
                'index' => route('admin.alerts.index'),
                'submit' => $alert ? route('admin.alerts.update', $alert) : route('admin.alerts.store'),
            ],
        ]);
    }
}
