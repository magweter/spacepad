<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Enums\DisplayStatus;
use App\Events\UserRegistered;
use App\Services\OutlookService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function __construct(protected OutlookService $outlookService)
    {
    }

    /**
     * @return Application|Factory|View
     * @throws \Exception
     */
    public function index(): View|Factory|Application
    {
        $user = auth()->user();

        // Register email verified if not a social auth user and publish the registered event
        if (! $user->hasVerifiedEmail() && ! $user->microsoft_id && ! $user->google_id) {
            $user->update(['email_verified_at' => now()]);
            event(new UserRegistered($user));
        }

        return view('pages.onboarding', [
            'outlookAccounts' => $user->outlookAccounts,
            'googleAccounts' => $user->googleAccounts,
        ]);
    }
}
