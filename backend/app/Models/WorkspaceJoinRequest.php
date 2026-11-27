<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceJoinRequest extends Model
{
    use HasUlid;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    protected $fillable = ['workspace_id', 'user_id', 'status', 'decided_by_user_id', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
