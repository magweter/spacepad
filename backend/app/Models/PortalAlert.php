<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * An alert an admin sets by hand, such as "Microsoft 365 has an outage", shown at the top of every
 * portal page while it runs. It applies to every workspace, or only to workspaces with a calendar
 * account of one provider.
 */
class PortalAlert extends Model
{
    use HasFactory;
    use HasUlid;

    public const CACHE_KEY = 'portal-alerts';

    public const TONES = ['attention', 'action_required'];

    public const PROVIDERS = ['microsoft', 'google', 'caldav'];

    protected $fillable = ['title', 'body', 'tone', 'provider', 'starts_at', 'ends_at', 'created_by'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Started (or no start set) and not yet ended.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function status(): string
    {
        return match (true) {
            $this->ends_at !== null && $this->ends_at->lte(now()) => 'ended',
            $this->starts_at !== null && $this->starts_at->gt(now()) => 'scheduled',
            default => 'active',
        };
    }

    /**
     * The alerts the given workspace sees right now, newest first.
     *
     * The alerts that have not ended (scheduled ones too) are cached for a minute; the time window
     * and the provider are checked on every call, so an alert starts and ends on time.
     *
     * @return array<int, array{id: string, tone: string, title: string, body: ?string}>
     */
    public static function forWorkspace(?Workspace $workspace): array
    {
        if ($workspace === null || config('settings.is_self_hosted')) {
            return [];
        }

        /** @var Collection<int, PortalAlert> $open */
        $open = Cache::remember(self::CACHE_KEY, 60, fn () => self::query()
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get());

        $running = $open->filter(fn (PortalAlert $alert) => $alert->status() === 'active');
        if ($running->isEmpty()) {
            return [];
        }

        $providers = self::providersOf($workspace, $running->pluck('provider')->filter()->unique()->all());

        return $running
            ->filter(fn (PortalAlert $alert) => $alert->provider === null || in_array($alert->provider, $providers, true))
            ->sortByDesc(fn (PortalAlert $alert) => ($alert->starts_at ?? $alert->created_at)?->getTimestamp())
            ->map(fn (PortalAlert $alert) => [
                'id' => $alert->id,
                'tone' => $alert->tone,
                'title' => $alert->title,
                'body' => $alert->body,
            ])
            ->values()
            ->all();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Which of the asked providers the workspace has a calendar account for.
     *
     * @param  array<int, string>  $asked
     * @return array<int, string>
     */
    private static function providersOf(Workspace $workspace, array $asked): array
    {
        $models = ['microsoft' => OutlookAccount::class, 'google' => GoogleAccount::class, 'caldav' => CalDAVAccount::class];

        return array_values(array_filter($asked, fn (string $provider) => isset($models[$provider])
            && $models[$provider]::where('workspace_id', $workspace->id)->exists()));
    }
}
