<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OAuthDriver;
use App\Events\UserRegistered;
use App\Http\Requests\Auth\OAuth2TokenRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Spatie\GoogleTagManager\GoogleTagManagerFacade as GoogleTagManager;

abstract class SocialAuthController extends AuthController
{
    protected string $driver;

    public function redirect(): mixed
    {
        try {
            // Stateful on purpose: Socialite then binds the flow to this session and refuses
            // a callback whose state it did not issue, which is what stops login CSRF.
            return Socialite::driver($this->driver)->redirect();
        } catch (\Exception $e) {
            report($e);
            logger()->error('Social redirect failed', [
                'provider' => $this->driver,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')->with('error', 'Redirecting to the provider failed. Please try again.');
        }
    }

    public function callback(): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($this->driver)->user();

            // An identity we already linked is its own credential. A new one is matched
            // on e-mail, so the provider has to vouch for that address first.
            $alreadyLinked = User::where($this->driver.'_id', $socialUser->getId())->exists();

            if (! $alreadyLinked && ! $this->providerVerifiedEmail($socialUser)) {
                logger()->warning('Social login refused: provider has not verified the e-mail address', [
                    'provider' => $this->driver,
                    'ip' => request()->ip(),
                ]);

                return redirect()
                    ->route('login')
                    ->with('error', 'Your '.Str::ucfirst($this->driver).' account has no verified e-mail address. Verify it with '.Str::ucfirst($this->driver).' first, or sign in with a magic link.');
            }

            if (! User::isAllowedLogin($socialUser->getEmail())) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Your organization or email is not allowed to log in.');
            }

            $user = $this->findOrCreateUser($socialUser);

            return $this->authenticateUser($user);
        } catch (\Exception $e) {
            report($e);
            logger()->error('Social authentication failed', [
                'provider' => $this->driver,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('login')
                ->with('error', 'Authentication with '.Str::ucfirst($this->driver).' failed. Please try again.');
        }
    }

    /**
     * @throws \Throwable
     */
    public function token(OAuth2TokenRequest $oauthTokenRequest): RedirectResponse
    {
        $socialUser = $this->getSocialUserFromToken($oauthTokenRequest);

        $this->validateSocialUser($socialUser);
        if (! User::isAllowedLogin($socialUser->getEmail())) {
            return redirect()
                ->route('login')
                ->with('error', 'Your organization or email is not allowed to log in.');
        }

        $user = $this->findOrCreateUser($socialUser);

        return $this->authenticateUser($user);
    }

    private function getSocialUserFromToken(OAuth2TokenRequest $oauthTokenRequest): mixed
    {
        $socialUser = null;
        $socialiteDriver = Socialite::driver($this->driver);

        try {
            $token = $oauthTokenRequest->token;
            $socialUser = $socialiteDriver->userFromToken($token);
            if (empty($socialUser->getName()) && ! empty($oauthTokenRequest->full_name)) {
                $socialUser->name = $oauthTokenRequest->full_name;
            }
        } catch (\Exception $e) {
            report($e);
            logger()->error('Something went wrong during OAuth2 authentication', [
                'provider' => $this->driver,
                'exception' => $e,
            ]);
        }

        return $socialUser;
    }

    /**
     * @throws \Throwable
     */
    private function validateSocialUser($socialUser): void
    {
        if (empty($socialUser) ||
            empty($socialUser->getId()) ||
            empty($socialUser->getName()) ||
            empty($socialUser->getEmail())) {
            logger()->error('One or more required properties were empty during OAuth2 authentication', [
                'provider' => $this->driver,
                'user_id' => $socialUser?->getId(),
            ]);

            throw_if(empty($socialUser), ValidationException::withMessages(['token' => ['required']]));
            throw_if(empty($socialUser->getId()), ValidationException::withMessages(['id' => ['required']]));
            throw_if(empty($socialUser->getName()), ValidationException::withMessages(['name' => ['required']]));
            throw_if(empty($socialUser->getEmail()), ValidationException::withMessages(['email' => ['required']]));
        }
    }

    /**
     * Whether the provider vouches for the e-mail address. An account is matched on that
     * address (findOrCreateUser), so an unverified one would let anybody who can register
     * a provider identity under a victim's address take the victim's account over.
     *
     * Google sends an explicit flag. Microsoft's e-mail is the userPrincipalName, which
     * can only carry a domain verified in that tenant, so it counts as verified.
     */
    protected function providerVerifiedEmail(mixed $socialUser): bool
    {
        if ($this->driver !== OAuthDriver::GOOGLE) {
            return true;
        }

        $raw = $socialUser->getRaw();

        return ($raw['email_verified'] ?? $raw['verified_email'] ?? false) === true;
    }

    protected function findOrCreateUser(mixed $socialUser): User
    {
        // first try to lookup the user by token
        $user = User::where($this->driver.'_id', $socialUser->getId())->first();
        if (empty($user)) {
            // getting here means there is no user connected to this social provider
            // check if this user has logged in using another provider or via email
            $user = User::whereEmail($socialUser->getEmail())->first();

            // if there still is no match, create a new user
            if (empty($user)) {
                $user = $this->createUser($socialUser->getName(), $socialUser->getEmail());

                GoogleTagManager::flashPush([
                    'event' => 'sign_up',
                ]);
                event(new UserRegistered($user));
            }

            // connect user to the social provider
            $user->update([$this->driver.'_id' => $socialUser->getId()]);
        }

        return $user;
    }

    protected function authenticateUser(User $user): RedirectResponse
    {
        auth()->login($user);

        return redirect()->route('dashboard');
    }
}
