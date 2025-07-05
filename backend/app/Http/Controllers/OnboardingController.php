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
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function __construct(protected OutlookService $outlookService)
    {
    }

    /**
     * @return Application|Factory|View
     * @throws \Exception
     */
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();
        $isSelfHosted = config('settings.is_self_hosted');

        // Register email verified if not a social auth user and publish the registered event
        if (! $user->hasVerifiedEmail() && ! $user->microsoft_id && ! $user->google_id) {
            $user->update(['email_verified_at' => now()]);
            event(new UserRegistered($user));
        }

        return view('pages.onboarding', [
            'hasUsageType' => $user->usage_type !== null,
            'hasAcceptedTerms' => ! $isSelfHosted || $user->terms_accepted_at !== null,
            'hasAnyAccount' => $user->hasAnyAccount(),
        ]);
    }

    public function updateUsageType(Request $request): RedirectResponse
    {
        $request->validate([
            'usage_type' => 'required|in:business,personal',
        ]);

        auth()->user()->update([
            'usage_type' => $request->usage_type,
        ]);

        return redirect()->route('dashboard');
    }

    public function acceptTerms(): RedirectResponse
    {
        auth()->user()->update([
            'terms_accepted_at' => now(),
        ]);

        return redirect()->route('dashboard');
    }
}
