<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasUlid, HasFactory;

    protected $fillable = [
        'display_id',
        'user_id',
        'start',
        'end',
        'summary',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];

    public function display(): BelongsTo
    {
        return $this->belongsTo(Display::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
