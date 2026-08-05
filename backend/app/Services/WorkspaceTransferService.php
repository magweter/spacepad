<?php

namespace App\Services;

use App\Models\Board;
use App\Models\CalDAVAccount;
use App\Models\Calendar;
use App\Models\Device;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Room;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Moving everything a workspace owns into another workspace.
 *
 * Used both when an invitee opts to bring their own data along, and by the admin merge
 * tool for consolidating accounts that were created separately.
 */
class WorkspaceTransferService
{
    public function __construct(private WorkspaceUsageService $usage) {}

    /**
     * Models that carry workspace_id and move wholesale.
     *
     * Accounts before calendars, and profiles before displays, purely so the log reads in
     * a sensible order — every relation is by id, so nothing can dangle.
     *
     * @var array<int, class-string<Model>>
     */
    private const WORKSPACE_MODELS = [
        OutlookAccount::class,
        GoogleAccount::class,
        CalDAVAccount::class,
        Calendar::class,
        Room::class,
        DisplayProfile::class,
        Display::class,
        Device::class,
        Board::class,
    ];

    /**
     * Models that can be orphaned by the old session-based workspace stamping.
     *
     * @var array<int, class-string<Model>>
     */
    private const ORPHANABLE_MODELS = [
        OutlookAccount::class,
        GoogleAccount::class,
        CalDAVAccount::class,
        Calendar::class,
        Room::class,
        Display::class,
        Device::class,
    ];

    /**
     * Count what a move would touch, without touching it.
     *
     * @return array<string, int>
     */
    public function preview(Workspace $from, ?User $actor = null, bool $includeOrphans = false): array
    {
        $counts = [];

        foreach (self::WORKSPACE_MODELS as $model) {
            $counts[class_basename($model)] = $model::where('workspace_id', $from->id)->count();
        }

        if ($includeOrphans && $actor) {
            foreach (self::ORPHANABLE_MODELS as $model) {
                $counts['orphaned'.class_basename($model)] = $model::where('user_id', $actor->id)
                    ->whereNull('workspace_id')
                    ->count();
            }
        }

        return array_filter($counts);
    }

    /**
     * Move every workspace-scoped row from one workspace to another.
     *
     * `user_id` is deliberately left alone: it records who created a row, not who owns it.
     * That is safe because user_id is nullable and nulls out on delete (see the
     * 2026_07_30_000000 migration) — before that migration this would have been a
     * data-loss bug, since deleting the original creator would cascade away rows now
     * living in a shared workspace.
     *
     * Events, event subscriptions, display settings and profile settings have no
     * workspace_id: they hang off their display, calendar or profile and follow along
     * automatically.
     *
     * @return array<string, int> moved row counts per model
     */
    public function move(Workspace $from, Workspace $to, ?User $actor = null, bool $adoptOrphans = false): array
    {
        if ($from->id === $to->id) {
            return [];
        }

        return DB::transaction(function () use ($from, $to, $actor, $adoptOrphans) {
            $moved = [];

            foreach (self::WORKSPACE_MODELS as $model) {
                $count = $model::where('workspace_id', $from->id)->update(['workspace_id' => $to->id]);

                if ($count > 0) {
                    $moved[class_basename($model)] = $count;
                }
            }

            // Adopt rows that were never stamped with a workspace. They are invisible to
            // every workspace-scoped query and policy, so they would otherwise be lost.
            if ($adoptOrphans && $actor) {
                foreach (self::ORPHANABLE_MODELS as $model) {
                    $count = $model::where('user_id', $actor->id)
                        ->whereNull('workspace_id')
                        ->update(['workspace_id' => $to->id]);

                    if ($count > 0) {
                        $moved['orphaned'.class_basename($model)] = $count;
                    }
                }
            }

            $this->detachCrossWorkspaceLinks($to);

            // The moves above are mass updates, so no model event fired and neither
            // workspace's usage counters know anything happened. Recounting both is the
            // repair, and it is what tells Lemon Squeezy the receiving workspace just
            // grew.
            $this->usage->recount($from->refresh());
            $this->usage->recount($to->refresh());

            logger()->info('Workspace data moved', [
                'from_workspace_id' => $from->id,
                'to_workspace_id' => $to->id,
                'actor_user_id' => $actor?->id,
                'adopt_orphans' => $adoptOrphans,
                'moved' => $moved,
            ]);

            return $moved;
        });
    }

    /**
     * Clean up links that would now span two workspaces.
     *
     * A whole-workspace move keeps boards and their displays together, so this is
     * belt-and-braces for partial moves and for data that was already inconsistent.
     */
    private function detachCrossWorkspaceLinks(Workspace $to): void
    {
        // board_displays has no workspace_id of its own; drop rows whose board and display
        // ended up in different workspaces. Reads already filter these out
        // (Board::getDisplaysToShowQuery), but leaving them is confusing.
        DB::table('board_displays')
            ->whereIn('board_id', Board::where('workspace_id', $to->id)->select('id'))
            ->whereIn('display_id', Display::where('workspace_id', '!=', $to->id)->select('id'))
            ->delete();

        // A display must never inherit settings from a profile in another workspace.
        Display::where('workspace_id', $to->id)
            ->whereNotNull('display_profile_id')
            ->whereIn('display_profile_id', DisplayProfile::where('workspace_id', '!=', $to->id)->select('id'))
            ->update(['display_profile_id' => null]);
    }
}
