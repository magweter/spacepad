<?php

namespace App\Support;

/**
 * The routes the sign-in page posts to and links to, handed to the Vue page as props.
 */
final class AuthPageUrls
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'login' => route('login'),
            'loginStore' => route('login.store'),
            'register' => route('register'),
            'registerStore' => route('register.store'),
            'registerResend' => route('register.resend'),
            'microsoft' => route('auth.microsoft.redirect'),
            'google' => route('auth.google.redirect'),
            'terms' => 'https://spacepad.io/terms',
            'privacy' => 'https://spacepad.io/privacy-policy',
            'dpa' => 'https://spacepad.io/dpa',
            'website' => 'https://spacepad.io',
        ];
    }
}
