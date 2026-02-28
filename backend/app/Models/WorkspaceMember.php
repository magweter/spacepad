<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    use HasFactory, HasUlid;

    protected $table = 'workspace_members';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => WorkspaceRole::class,
    ];

    /**
     * Get the workspace this member belongs to
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user member
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

