<?php

namespace App\Data;

class PermissionResult
{
    public readonly bool $permitted;
    public readonly ?string $message;
    public readonly ?int $code;

    public function __construct(bool $permitted, ?string $message = null, ?int $code = null)
    {
        $this->permitted = $permitted;
        $this->message = $message;
        $this->code = $code;
    }
} 