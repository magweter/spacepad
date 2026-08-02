<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    use HasFactory, HasUlid;

    /**
     * How long an invitation link stays usable.
     */
    public const LIFETIME_DAYS = 7;

    protected $fillable = [
        'workspace_id',
        'invited_by_user_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'accepted_by_user_id',
    ];

    protected $casts = [
        'role' => WorkspaceRole::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * The plaintext token, set only on the instance that just created or rotated it so the
     * notification can build a URL. Never persisted.
     */
    public ?string $plainToken = null;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /**
     * Invitations that can still be accepted.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * Hash a plaintext token the same way it is stored.
     */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
