<?php

namespace App\Support;

use App\Models\User;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;

/**
 * The one place a login link is minted, so every link has the same short life and is
 * good for a single visit. A link is a password that travels by e-mail: the shorter it
 * lives and the fewer times it works, the less a forwarded or logged copy is worth.
 */
class MagicLoginLink
{
    public const LIFETIME_MINUTES = 15;

    public static function create(User $user): string
    {
        return MagicLink::create(new LoginAction($user), self::LIFETIME_MINUTES, 1)->url;
    }
}
