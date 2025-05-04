<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\MagicLoginNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;

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
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            $user = User::factory()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'email_verified_at' => null,
            ]);
        }

        $loginUrl = MagicLink::create(new LoginAction($user))->url;
        $user->notify(new MagicLoginNotification($loginUrl));

        return redirect()
            ->back()
            ->with('registered', true);
    }
}
