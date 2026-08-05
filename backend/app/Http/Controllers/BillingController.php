<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\GoogleTagManager\GoogleTagManagerFacade;

class BillingController extends Controller
{
    /**
     * Start a Pro checkout for the current workspace.
     *
     * A POST route rather than a link built during rendering: the vendor's Checkout::url()
     * performs a live API call to Lemon Squeezy, so the old approach hit their API on every
     * dashboard render for every non-Pro user. It also gives the owner check a natural home.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();

        abort_unless($workspace !== null, 404, 'No workspace found');
        $this->authorize('manageBilling', $workspace);

        // Lemon Squeezy happily sells a second subscription for the same workspace, and
        // Checkout::url() asks it to without ever looking at what is already there. A trial
        // counts as subscribed, so this also covers the window in which someone is trialling
        // and follows a stale tab or an old link back to the checkout.
        if ($workspace->subscribed()) {
            return redirect()->route('workspaces.members')
                ->with('info', 'This workspace already has a subscription.');
        }

        $checkout = $workspace->getCheckoutUrl(route('billing.thanks'));

        abort_if($checkout === null, 404);

        return redirect()->away($checkout->url());
    }

    public function thanks(): RedirectResponse
    {
        GoogleTagManagerFacade::flashPush([
            'event' => 'purchase',
        ]);

        if (config('services.google_conversion.send_to')) {
            GoogleTagManagerFacade::flashPush([
                'event' => 'conversion',
                'send_to' => config('services.google_conversion.send_to'),
                'value' => config('services.google_conversion.value'),
                'currency' => config('services.google_conversion.currency'),
                'transaction_id' => '',
            ]);
        }

        return redirect()->route('dashboard');
    }
}
