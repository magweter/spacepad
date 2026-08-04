<?php

namespace App\Models;

use App\Traits\HasEncryptedTypedValue;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisplaySetting extends Model
{
    use HasEncryptedTypedValue;
    use HasUlid;

    protected $fillable = [
        'display_id',
        'key',
        'value',
        'type',
    ];

    protected $casts = [
        'value' => 'encrypted',
    ];

    public function display(): BelongsTo
    {
        return $this->belongsTo(Display::class);
    }
}
