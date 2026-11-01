<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Notifications\MagicLoginNotification;
use App\Support\MagicLoginLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (config('settings.disable_email_login')) {
            return redirect()->back()->withErrors(['email' => 'Email login is disabled.']);
        }

        $data = $request->validated();

        if (! User::isAllowedLogin($data['email'])) {
            return redirect()->back()->withErrors(['email' => 'Your organization or email is not allowed to log in.']);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            $user = User::factory()->unverified()->create([
                'name' => Str::before($data['email'], '@'),
                'email' => $data['email'],
            ]);
        }

        $loginUrl = MagicLoginLink::create($user);
        $user->notify(new MagicLoginNotification($loginUrl));

        return redirect()
            ->back()
            ->with('success', 'Check your e-mail. You should receive an e-mail with a login link shortly.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->intended('/');
    }
}
