<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Requests\Auth\OAuth2TokenRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

abstract class SocialAuthController extends AuthController
{
    protected string $driver;

    public function redirect(): mixed
    {
        try {
            return Socialite::driver($this->driver)->stateless()->redirect();
        } catch (\Exception $e) {
            report($e);
            logger()->error('Social redirect failed', [
                'provider' => $this->driver,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('login')->with('error', 'Redirecting to the provider failed. Please try again.');
        }
    }

    public function callback(): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($this->driver)->stateless()->user();
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
                'error' => $e->getMessage()
            ]);
            return redirect()
                ->route('login')
                ->with('error', 'Authentication with ' . Str::ucfirst($this->driver) . ' failed. Please try again.');
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
                'user' => $socialUser,
            ]);

            throw_if(empty($socialUser), ValidationException::withMessages(['token' => ['required']]));
            throw_if(empty($socialUser->getId()), ValidationException::withMessages(['id' => ['required']]));
            throw_if(empty($socialUser->getName()), ValidationException::withMessages(['name' => ['required']]));
            throw_if(empty($socialUser->getEmail()), ValidationException::withMessages(['email' => ['required']]));
        }
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
