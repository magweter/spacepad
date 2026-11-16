<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * What an email address says about the organisation behind it.
 *
 * A company domain (cbyte.nl) names the organisation and finds colleagues; a freemail domain
 * (gmail.com) says nothing about either.
 */
final class EmailDomain
{
    /**
     * Mail providers anyone can sign up with. Not exhaustive; it covers the ones people
     * actually register with.
     */
    private const FREEMAIL = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'hotmail.nl', 'live.com', 'live.nl',
        'msn.com', 'yahoo.com', 'yahoo.co.uk', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'proton.me',
        'protonmail.com', 'gmx.com', 'gmx.de', 'gmx.net', 'web.de', 'mail.com', 'zoho.com', 'yandex.com',
        'ziggo.nl', 'kpnmail.nl', 'planet.nl', 'home.nl', 'xs4all.nl', 'telenet.be', 'skynet.be',
        'orange.fr', 'free.fr', 'laposte.net', 'hey.com', 'fastmail.com',
    ];

    public static function of(string $email): string
    {
        return Str::lower(Str::afterLast(trim($email), '@'));
    }

    public static function isCompany(string $email): bool
    {
        $domain = self::of($email);

        return $domain !== '' && str_contains($domain, '.') && ! in_array($domain, self::FREEMAIL, true);
    }

    /**
     * "Cbyte" for cbyte.nl, "Acme Corp" for acme-corp.co.uk. Null for a freemail address.
     */
    public static function companyName(string $email): ?string
    {
        if (! self::isCompany($email)) {
            return null;
        }

        $label = Str::before(self::of($email), '.');

        return Str::title(str_replace(['-', '_'], ' ', $label));
    }
}
