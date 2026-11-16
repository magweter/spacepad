<?php

namespace App\Http\Controllers;

use App\Enums\FunnelStep;
use App\Notifications\EnterpriseInterest;
use App\Services\Funnel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Enterprise from the upgrade dialog: the workspace starts with the Pro trial like anyone else,
 * and sales hears about it so they can reach out about a demo, an SLA and a quote.
 */
class EnterpriseInterestController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();

        abort_unless($workspace !== null, 404, 'No workspace found');
        $this->authorize('manageBilling', $workspace);

        // One email per workspace an hour, however often the button is pressed.
        $key = 'enterprise-interest:'.$workspace->id;
        if (! RateLimiter::tooManyAttempts($key, 1) && config('settings.sales_email')) {
            RateLimiter::hit($key, 3600);

            Notification::route('mail', config('settings.sales_email'))->notify(new EnterpriseInterest(
                requesterName: $user->name,
                requesterEmail: $user->email,
                workspaceName: $workspace->name,
                displays: $workspace->displays()->count(),
            ));

            Funnel::record(FunnelStep::ENTERPRISE_INTEREST, $workspace, $user);
        }

        // Already on Pro (a subscription, a trial, or billed by hand): nothing to check out, the
        // email is what counted.
        if ($workspace->hasPro() || config('settings.is_self_hosted')) {
            return redirect()->route('billing.index')
                ->with('success', 'Thanks, we will contact you about Enterprise.');
        }

        $checkout = $workspace->getCheckoutUrl(route('billing.thanks'));
        abort_if($checkout === null, 404);

        return redirect()->away($checkout->url());
    }
}
