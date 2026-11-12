<?php

namespace App\Http\Controllers;

use App\Enums\CancellationReason;
use App\Models\SubscriptionCancellation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\GoogleTagManager\GoogleTagManagerFacade;
use Throwable;

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
            return redirect()->route('billing.index')
                ->with('info', 'This workspace already has a subscription.');
        }

        $checkout = $workspace->getCheckoutUrl(route('billing.thanks'));

        abort_if($checkout === null, 404);

        return redirect()->away($checkout->url());
    }

    /**
     * Send the owner to the Lemon Squeezy customer portal, where they can change their
     * payment method, download invoices and cancel.
     *
     * POSTed for the same reason as the checkout: the portal url comes from a live API call,
     * so a link built while rendering would hit Lemon Squeezy on every view of the page.
     */
    public function portal(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();

        abort_unless($workspace !== null, 404, 'No workspace found');
        $this->authorize('manageBilling', $workspace);
        abort_unless($workspace->hasBillingPortal(), 404);

        try {
            return redirect()->away($workspace->customerPortalUrl());
        } catch (Throwable $e) {
            // Their API being down should leave someone on the page they came from with an
            // explanation, not on an error screen.
            report($e);

            return redirect()->route('billing.index')
                ->with('error', 'We could not open the billing portal just now. Please try again, or email support@spacepad.io.');
        }
    }

    /**
     * Move a monthly workspace to yearly through a fresh checkout.
     *
     * A checkout rather than a swap on the existing subscription: the customer is present to
     * pay the year and to pass 3D Secure, so a card that refuses the amount is found out here
     * instead of a month later on an off-session charge. The monthly subscription is
     * cancelled by CancelSupersededSubscription once the yearly one exists, so an abandoned
     * checkout changes nothing.
     */
    public function switchToYearly(Request $request): RedirectResponse
    {
        $workspace = $request->user()->getSelectedWorkspace();

        abort_unless($workspace !== null, 404, 'No workspace found');
        $this->authorize('manageBilling', $workspace);

        if (! $workspace->canSwitchToYearly()) {
            return redirect()->route('billing.index')
                ->with('error', 'This workspace cannot switch to a yearly subscription right now. Email support@spacepad.io if you think it should.');
        }

        try {
            return redirect()->away($workspace->getYearlySwitchCheckoutUrl());
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('billing.index')
                ->with('error', 'We could not open the checkout just now. Please try again, or email support@spacepad.io.');
        }
    }

    /**
     * Ask for a yearly workspace to go back to monthly when the paid year ends.
     *
     * Only recorded here. app:process-yearly-subscriptions swaps the variant on the last day
     * of the paid year, which is when Lemon Squeezy starts and invoices the monthly cycle.
     */
    public function switchToMonthly(Request $request): RedirectResponse
    {
        $workspace = $request->user()->getSelectedWorkspace();

        abort_unless($workspace !== null, 404, 'No workspace found');
        $this->authorize('manageBilling', $workspace);

        if (! $workspace->canSwitchToMonthly()) {
            return redirect()->route('billing.index')
                ->with('error', 'This workspace cannot switch to a monthly subscription right now. Email support@spacepad.io if you think it should.');
        }

        $workspace->forceFill(['switch_to_monthly_requested_at' => now()])->save();

        return redirect()->route('billing.index')
            ->with('success', 'Your subscription switches to monthly on the last day of your paid year.');
    }

    public function cancelSwitchToMonthly(Request $request): RedirectResponse
    {
        $workspace = $request->user()->getSelectedWorkspace();

        abort_unless($workspace !== null, 404, 'No workspace found');
        $this->authorize('manageBilling', $workspace);

        $workspace->forceFill(['switch_to_monthly_requested_at' => null])->save();

        return redirect()->route('billing.index')
            ->with('success', 'Your subscription stays yearly.');
    }

    /**
     * Cancel the workspace's subscription from the app, with the reason why.
     *
     * Lemon Squeezy cancels at the end of the paid period, so Pro stays until then. The
     * reason is only stored once the cancellation went through: a reason without a
     * cancellation behind it would say something that did not happen.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();

        abort_unless($workspace !== null, 404, 'No workspace found');
        $this->authorize('manageBilling', $workspace);

        $data = $this->validateReason($request);

        $subscription = $workspace->cancellableSubscription();

        if (! $subscription) {
            return redirect()->route('billing.index')
                ->with('info', 'This subscription is already cancelled.');
        }

        try {
            $subscription->cancel();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('billing.index')
                ->with('error', 'We could not cancel your subscription just now. Please try again, or email support@spacepad.io.');
        }

        SubscriptionCancellation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'lemon_squeezy_subscription_id' => $subscription->lemon_squeezy_id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'source' => SubscriptionCancellation::SOURCE_IN_APP,
            'answered_at' => now(),
        ]);

        $endsAt = $subscription->fresh()?->ends_at;

        return redirect()->route('billing.index')
            ->with('success', $endsAt
                ? 'Your subscription is cancelled. Pro stays active until '.$endsAt->format('j F Y').'.'
                : 'Your subscription is cancelled.');
    }

    /**
     * The reason for a cancellation that was made outside the app.
     */
    public function feedback(Request $request, SubscriptionCancellation $cancellation): RedirectResponse
    {
        $this->authorize('manageBilling', $cancellation->workspace);

        $data = $this->validateReason($request);

        $cancellation->update([
            'user_id' => $cancellation->user_id ?? $request->user()->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'answered_at' => now(),
        ]);

        return back()->with('success', 'Thanks for letting us know.');
    }

    public function dismissFeedback(SubscriptionCancellation $cancellation): RedirectResponse
    {
        $this->authorize('manageBilling', $cancellation->workspace);

        $cancellation->update(['dismissed_at' => now()]);

        return back();
    }

    /**
     * @return array{reason: string, details?: string|null}
     */
    private function validateReason(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', Rule::enum(CancellationReason::class)],
            'details' => ['nullable', 'required_if:reason,other', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Pick the reason that fits best.',
            'details.required_if' => 'Tell us a little more.',
        ]);
    }

    public function thanks(Request $request): RedirectResponse
    {
        // An existing customer switching to yearly is not a new purchase for ad reporting.
        // Lemon Squeezy only sends the customer back after a successful payment. The webhook
        // that cancels the monthly subscription arrives within seconds of it.
        if ($request->boolean('switched')) {
            return redirect()->route('dashboard')
                ->with('success', 'Your yearly subscription is active. Your monthly subscription has been stopped and will not be charged again.');
        }

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
