<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

/**
 * Shared encrypted, type-aware value handling for key/value setting models
 * (DisplaySetting and DisplayProfileSetting). The stored `value` is encrypted
 * at rest and decoded to its declared `type` on read.
 */
trait HasEncryptedTypedValue
{
    public function getValueAttribute($value)
    {
        if (! $value) {
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
