<?php

namespace App\Http\Controllers\Concerns;

trait VerifiesOAuthState
{
    /**
     * Compare the state the provider sent back with the one this session issued when it
     * started the flow. The session value is pulled, so a state is good for one callback.
     *
     * A failure here means the code in the callback was not requested by this session: it
     * was either planted (login CSRF on the link) or the flow was started elsewhere. Either
     * way the code must not be exchanged.
     */
    protected function stateMatchesSession(string $sessionKey): bool
    {
        $expected = session()->pull($sessionKey);
        $received = request('state');

        if (! is_string($expected) || $expected === '' || ! is_string($received)) {
            logger()->warning('OAuth callback without a matching state', [
                'session_key' => $sessionKey,
                'had_expected' => is_string($expected) && $expected !== '',
                'had_received' => is_string($received),
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]);

            return false;
        }

        if (! hash_equals($expected, $received)) {
            logger()->warning('OAuth callback with a state that does not match the session', [
                'session_key' => $sessionKey,
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]);

            return false;
        }

        return true;
    }
}
