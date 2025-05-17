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
        $outlookAccounts = auth()->user()->outlookAccounts;
        $googleAccounts = auth()->user()->googleAccounts;

        $user = auth()->user();
        if (! $user->email_verified_at && ! $user->microsoft_id) {
            $user->update(['email_verified_at' => now()]);
            event(new UserRegistered($user));
        }

        return view('pages.onboarding', [
            'outlookAccounts' => $outlookAccounts,
            'googleAccounts' => $googleAccounts,
        ]);
    }
}