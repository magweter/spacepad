<?php

namespace App\Models;

use App\Traits\HasUlid;
use App\Traits\HasLastActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

class Device extends Model implements Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUlid;
    use HasLastActivity;
    use AuthenticatableTrait;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'display_id',
        'name',
        'uid',
        'last_activity_at'
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function display(): BelongsTo
    {
        return $this->belongsTo(Display::class, 'display_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }
}
