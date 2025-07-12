<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DisplaySetting extends Model
{
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

    public function getValueAttribute($value)
    {
        if (!$value) {
            return null;
        }

        $decrypted = Crypt::decryptString($value);

        return match ($this->type) {
            'boolean' => filter_var($decrypted, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $decrypted,
            'float' => (float) $decrypted,
            'array' => json_decode($decrypted, true),
            'object' => json_decode($decrypted),
            default => $decrypted,
        };
    }

    public function setValueAttribute($value)
    {
        if ($value === null) {
            $this->attributes['value'] = null;
            return;
        }

        $this->attributes['value'] = Crypt::encryptString(
            is_array($value) || is_object($value) ? json_encode($value) : (string) $value
        );
    }
} 