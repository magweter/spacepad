<?php

namespace App\Models;

use App\Traits\HasEncryptedTypedValue;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisplayProfileSetting extends Model
{
    use HasEncryptedTypedValue;
    use HasUlid;

    protected $fillable = [
        'display_profile_id',
        'key',
        'value',
        'type',
    ];

    protected $casts = [
        'value' => 'encrypted',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DisplayProfile::class, 'display_profile_id');
    }
}
