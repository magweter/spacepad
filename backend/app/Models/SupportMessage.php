<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasUlid;

    protected $fillable = ['user_id', 'message', 'is_read'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
