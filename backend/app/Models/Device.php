<?php

namespace App\Models;

use App\Traits\HasUlid;
use App\Traits\HasLastActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    use HasApiTokens;
    use HasFactory;
    use HasUlid;
    use HasLastActivity;

    protected $fillable = [
        'user_id',
        'display_id',
        'name',
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
}
