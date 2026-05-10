<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\MagicLoginNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;
use Spatie\GoogleTagManager\GoogleTagManagerFacade as GoogleTagManager;

class RegisterController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming authentication request.
     *
     *
     * @throws ValidationException
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        if (config('settings.disable_email_login')) {
            return redirect()->back()->withErrors(['email' => 'Email registration is disabled.']);
        }

        $data = $request->validated();

        if (! User::isAllowedLogin($data['email'])) {
            return redirect()->back()->withErrors(['email' => 'Your organization or email is not allowed to register.']);
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            $user = User::factory()->unverified()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'terms_accepted_at' => ! config('settings.is_self_hosted') ? now() : null,
            ]);

            GoogleTagManager::flashPush([
                'event' => 'sign_up',
            ]);
        }

        $loginUrl = MagicLink::create(new LoginAction($user), 60 * 24)->url;
        $user->notify(new MagicLoginNotification($loginUrl));

        return redirect()
            ->back()
            ->with('registered', true)
            ->with('registered_email', $data['email']);
    }

    public function resend(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $key = 'resend:' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->route('register')
                ->with('registered', true)
                ->with('registered_email', $request->email)
                ->with('error', "Too many resend attempts. Please wait {$seconds} seconds before trying again.");
        }
        RateLimiter::hit($key, 600); // 3 attempts per 10 minutes per email

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $loginUrl = MagicLink::create(new LoginAction($user), 60 * 24)->url;
            $user->notify(new MagicLoginNotification($loginUrl));
        }

        return redirect()->route('register')
            ->with('registered', true)
            ->with('registered_email', $request->email)
            ->with('success', 'Email resent! Check your inbox (and spam folder).');
    }
}
