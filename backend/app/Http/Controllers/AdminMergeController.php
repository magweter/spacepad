<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Concerns\ChecksAdminAccess;
use App\Models\Board;
use App\Models\CalDAVAccount;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\FreePlanDisplayLimit;
use App\Services\WorkspaceTransferService;
use App\Support\AdminPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consolidating two workspaces that should have been one.
 *
 * Exists for the customers who each signed up separately before invitations existed. Kept
 * out of AdminController, which is already long, but follows the same conventions:
 * checkAdminAccess() at the top of every action, and one big explicit transaction.
 *
 * Irreversible, so the flow is preview-then-confirm and it deliberately never touches
 * subscriptions or de-duplicates calendar accounts.
 */
class AdminMergeController extends Controller
{
    use ChecksAdminAccess;

    public function __construct(
        protected WorkspaceTransferService $transfers,
        protected FreePlanDisplayLimit $freePlan,
    ) {}

    public function index(Request $request): Response
    {
        $this->checkAdminAccess();

        return $this->render($request, [
            'source' => $this->resolveWorkspace($request->input('source')),
            'target' => $this->resolveWorkspace($request->input('target')),
            'options' => self::DEFAULT_OPTIONS,
            'preview' => null,
        ]);
    }

    /**
     * Show what the merge would do. Must have no side effects — it is a POST only because
     * it takes the form body.
     */
    public function preview(Request $request): Response
    {
        $this->checkAdminAccess();

        $validated = $this->validateMerge($request);

        $source = Workspace::findOrFail($validated['source_workspace_id']);
        $target = Workspace::findOrFail($validated['target_workspace_id']);

        return $this->render($request, [
            'source' => $source,
            'target' => $target,
            'options' => $this->options($request),
            'preview' => [
                'counts' => $this->transfers->preview($source),
                'nameCollisions' => $this->nameCollisions($source, $target),
                'duplicateAccounts' => $this->duplicateAccounts($source, $target),
                'members' => $this->memberPlan($source, $target),
                'sourceHasSubscription' => $source->hasActiveSubscription(),
                'targetHasSubscription' => $target->hasActiveSubscription(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkAdminAccess();

        $validated = $this->validateMerge($request);

        $source = Workspace::findOrFail($validated['source_workspace_id']);
        $target = Workspace::findOrFail($validated['target_workspace_id']);
        $options = $this->options($request);
        $admin = Auth::user();

        if ($request->input('confirm_name') !== $source->name) {
            return back()->withErrors([
                'confirm_name' => 'The source workspace name does not match.',
            ])->withInput();
        }

        $sourceName = $source->name;

        DB::transaction(function () use ($source, $target, $options, $admin) {
            if ($options['rename_collisions']) {
                $this->renameCollisions($source, $target);
            }

            $moved = $this->transfers->move($source, $target, null, $options['adopt_orphans']);

            if ($options['move_members']) {
                $this->moveMembers($source, $target);
            }

            // Pending invitations for a workspace that is going away are meaningless.
            WorkspaceInvitation::where('workspace_id', $source->id)->whereNull('accepted_at')->delete();

            $deletedSource = false;
            if ($options['delete_source'] && $source->fresh()->isEmpty() && ! $source->hasActiveSubscription()) {
                WorkspaceMember::where('workspace_id', $source->id)->delete();
                $source->delete();
                $deletedSource = true;
            }

            logger()->info('Admin merged workspaces', [
                'admin_id' => $admin->id,
                'source_workspace_id' => $source->id,
                'target_workspace_id' => $target->id,
                'moved' => $moved,
                'options' => $options,
                'source_deleted' => $deletedSource,
            ]);
        });

        // Two free workspaces combined are still one free workspace: one active display.
        $this->freePlan->apply($target->fresh());

        return redirect()->route('admin.merge.index')
            ->with('success', "Merged \"{$sourceName}\" into \"{$target->name}\".");
    }

    /**
     * What the form starts with before anything was previewed.
     */
    private const DEFAULT_OPTIONS = [
        'move_members' => true,
        'rename_collisions' => true,
        'delete_source' => true,
        'adopt_orphans' => false,
    ];

    /**
     * @param  array{source: ?Workspace, target: ?Workspace, options: array<string, bool>, preview: ?array<string, mixed>}  $data
     */
    private function render(Request $request, array $data): Response
    {
        // With the owner's email, as the workspace picker shows it.
        $workspace = fn (?Workspace $w) => $w ? ['id' => $w->id, 'name' => $w->name, 'owner' => $w->billingOwner()?->email] : null;
        $preview = $data['preview'];

        if ($preview) {
            // Labels as the screen shows them; the transfer service keys its counts by table.
            $preview['counts'] = collect($preview['counts'])
                ->map(fn ($count, $label) => ['label' => Str::headline($label), 'count' => $count])
                ->values()->all();
            foreach (['nameCollisions', 'duplicateAccounts'] as $key) {
                $preview[$key] = collect($preview[$key])
                    ->map(fn ($items, $type) => ['type' => Str::headline($type), 'items' => $items])
                    ->values()->all();
            }
        }

        return Inertia::render('Admin/Merge', [
            ...AdminPage::props($request),
            'source' => $workspace($data['source']),
            'target' => $workspace($data['target']),
            'options' => $data['options'],
            'preview' => $preview,
            'urls' => [
                'preview' => route('admin.merge.preview'),
                'store' => route('admin.merge.store'),
                'lookup' => route('admin.workspaces.lookup'),
            ],
        ]);
    }

    /**
     * @return array{source_workspace_id: string, target_workspace_id: string}
     */
    private function validateMerge(Request $request): array
    {
        return $request->validate([
            'source_workspace_id' => ['required', 'string', 'exists:workspaces,id'],
            'target_workspace_id' => ['required', 'string', 'exists:workspaces,id', 'different:source_workspace_id'],
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function options(Request $request): array
    {
        return [
            'move_members' => $request->boolean('move_members'),
            'rename_collisions' => $request->boolean('rename_collisions'),
            'delete_source' => $request->boolean('delete_source'),
            'adopt_orphans' => $request->boolean('adopt_orphans'),
        ];
    }

    private function resolveWorkspace(?string $id): ?Workspace
    {
        return $id ? Workspace::find($id) : null;
    }

    /**
     * Names that exist on both sides. Legal — there are no unique constraints — but
     * confusing on a single dashboard.
     *
     * @return array<string, array<int, string>>
     */
    private function nameCollisions(Workspace $source, Workspace $target): array
    {
        $collisions = [];

        foreach ([Display::class, Board::class, DisplayProfile::class] as $model) {
            $targetNames = $model::where('workspace_id', $target->id)->pluck('name')->map('strtolower');

            $clashing = $model::where('workspace_id', $source->id)
                ->pluck('name')
                ->filter(fn ($name) => $targetNames->contains(strtolower((string) $name)))
                ->values()
                ->all();

            if ($clashing !== []) {
                $collisions[class_basename($model)] = $clashing;
            }
        }

        return $collisions;
    }

    /**
     * Calendar accounts for the same mailbox on both sides.
     *
     * Reported, never merged: uniqueness on these tables was deliberately removed, the
     * OAuth tokens differ, and re-pointing calendars at another account row would break
     * sync and the per-account event subscriptions.
     *
     * @return array<string, array<int, string>>
     */
    private function duplicateAccounts(Workspace $source, Workspace $target): array
    {
        $duplicates = [];

        foreach ([OutlookAccount::class, GoogleAccount::class, CalDAVAccount::class] as $model) {
            $targetEmails = $model::where('workspace_id', $target->id)->pluck('email')->map('strtolower');

            $clashing = $model::where('workspace_id', $source->id)
                ->pluck('email')
                ->filter(fn ($email) => $targetEmails->contains(strtolower((string) $email)))
                ->values()
                ->all();

            if ($clashing !== []) {
                $duplicates[class_basename($model)] = $clashing;
            }
        }

        return $duplicates;
    }

    /**
     * What would happen to each of the source workspace's members.
     *
     * @return array<int, array{email: string, from: string, to: string}>
     */
    private function memberPlan(Workspace $source, Workspace $target): array
    {
        $plan = [];

        foreach ($source->members as $member) {
            $from = WorkspaceRole::fromPivot($member->pivot->role);
            $existing = $target->getUserRole($member);

            $plan[] = [
                'email' => $member->email,
                'from' => $from->label(),
                'to' => $existing
                    ? $existing->label().' (already a member, unchanged)'
                    : $this->mapRole($from)->label(),
            ];
        }

        return $plan;
    }

    /**
     * A source owner becomes an admin in the target: merging must not hand over ownership
     * of the surviving workspace.
     */
    private function mapRole(WorkspaceRole $role): WorkspaceRole
    {
        return match ($role) {
            WorkspaceRole::OWNER, WorkspaceRole::ADMIN => WorkspaceRole::ADMIN,
            WorkspaceRole::MEMBER => WorkspaceRole::MEMBER,
        };
    }

    /**
     * Add the source members to the target, never downgrading an existing role.
     */
    private function moveMembers(Workspace $source, Workspace $target): void
    {
        foreach ($source->members as $member) {
            WorkspaceMember::firstOrCreate(
                [
                    'workspace_id' => $target->id,
                    'user_id' => $member->id,
                ],
                ['role' => $this->mapRole(WorkspaceRole::fromPivot($member->pivot->role))]
            );
        }
    }

    /**
     * Suffix source-side names that clash, so both remain identifiable afterwards.
     */
    private function renameCollisions(Workspace $source, Workspace $target): void
    {
        foreach ([Display::class, Board::class, DisplayProfile::class] as $model) {
            $targetNames = $model::where('workspace_id', $target->id)->pluck('name')->map('strtolower');

            foreach ($model::where('workspace_id', $source->id)->get() as $row) {
                if ($targetNames->contains(strtolower((string) $row->name))) {
                    $row->update(['name' => "{$row->name} ({$source->name})"]);
                }
            }
        }
    }
}
