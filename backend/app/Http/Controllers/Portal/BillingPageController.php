<?php

namespace App\Http\Controllers\Portal;

use App\Enums\CancellationReason;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\BillingPlans;
use App\Services\InstanceService;
use App\Support\PortalShell;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace's plan, what it uses and what can be done about the subscription.
 *
 * Billing belongs to the workspace, so everyone in it sees what it is billed for, but only owners
 * and admins (manageBilling) get the controls. Nothing here asks Lemon Squeezy anything while
 * rendering: the checkout, portal and yearly switch urls are fetched behind their POST routes.
 */
class BillingPageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        $selfHosted = (bool) config('settings.is_self_hosted');
        $canManage = $user->can('manageBilling', $workspace);
        $usage = $workspace->getUsageBreakdown();
        // A trial is a real subscription that converts on its own, so it is shown as a countdown
        // rather than as something to buy.
        $subscription = $selfHosted ? null : $workspace->subscription();
        // Price and currency both follow the billing route, see getQuotedUnitPrice(). Null means
        // there is nothing to quote and the cost lines are left out.
        $unitPrice = $workspace->getQuotedUnitPrice();
        $cancellable = $canManage && ! $selfHosted ? $workspace->cancellableSubscription() : null;

        return Inertia::render('Billing/Index', [
            'shell' => PortalShell::for($request, $workspace, 'billing'),
            'billing' => [
                'canManage' => $canManage,
                'hasPro' => $workspace->hasPro(),
                'selfHosted' => $selfHosted,
                'state' => $this->state($workspace, $canManage, $selfHosted),
                'interval' => $this->intervalLabel($workspace, $selfHosted),
                'usage' => [
                    'displays' => $usage['displays'],
                    'boards' => $usage['boards'],
                    'total' => $usage['total'],
                ],
                'unitPrice' => $unitPrice,
                'currency' => $workspace->getQuotedCurrencySymbol(),
                'monthlyCost' => $unitPrice === null ? null : round($unitPrice * $usage['total'], 2),
                'trial' => $subscription?->onTrial() && $subscription->trial_ends_at ? [
                    'daysLeft' => (int) ceil(now()->diffInDays($subscription->trial_ends_at, false)),
                    'endsOn' => $subscription->trial_ends_at->format('j F Y'),
                ] : null,
                'switch' => $canManage ? $this->switch($workspace, $subscription, $usage['total']) : null,
                'cancel' => $cancellable ? ['endsOn' => $cancellable->renews_at?->format('j F Y')] : null,
                'cancelledUntil' => $canManage && ! $cancellable && $subscription?->onGracePeriod() && $subscription->ends_at
                    ? $subscription->ends_at->format('j F Y')
                    : null,
                // Who a member can go to, since they cannot pay themselves.
                'managers' => $canManage ? [] : $workspace->members()
                    ->wherePivotIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
                    ->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
                    ->map(fn ($m) => ['name' => $m->name, 'email' => $m->email])->values()->all(),
            ],
            'license' => $selfHosted ? $this->license() : null,
            'cancellationReasons' => $cancellable ? collect(CancellationReason::cases())
                ->map(fn ($r) => ['value' => $r->value, 'label' => $r->label(), 'prompt' => $r->detailsPrompt()])->all() : [],
            'urls' => [
                'checkout' => route('billing.checkout'),
                'portal' => route('billing.portal'),
                'switchYearly' => route('billing.switch-yearly'),
                'switchMonthly' => route('billing.switch-monthly'),
                'keepYearly' => route('billing.switch-monthly.cancel'),
                'cancel' => route('billing.cancel'),
                'license' => route('license.validate'),
                'team' => route('workspaces.members'),
            ],
        ]);
    }

    /**
     * What this workspace can do about its subscription, decided once so the note and the button
     * that goes with it never drift apart.
     */
    private function state(Workspace $workspace, bool $canManage, bool $selfHosted): ?string
    {
        return match (true) {
            ! $canManage => $workspace->hasPro() ? 'others' : 'ask',
            // We invoice a manually billed workspace ourselves and an unlimited one is not
            // charged at all, so neither has a subscription to open or buy.
            (bool) $workspace->is_manually_billed => 'manual',
            // Cloud only: on a self-hosted instance Pro comes from an instance licence.
            $workspace->is_unlimited && ! $selfHosted => 'unlimited',
            $workspace->hasPro() => $workspace->hasBillingPortal() ? 'portal' : 'modal',
            ! $selfHosted => 'checkout',
            default => null,
        };
    }

    /**
     * How the live Lemon Squeezy subscription is billed, in a few words. Null when there is no
     * such subscription: manual and unlimited workspaces explain themselves already.
     */
    private function intervalLabel(Workspace $workspace, bool $selfHosted): ?string
    {
        if ($selfHosted || $workspace->is_manually_billed || $workspace->is_unlimited) {
            return null;
        }

        $subscription = $workspace->liveSubscription();

        if ($subscription === null) {
            return null;
        }

        $interval = BillingPlans::isYearly($subscription) ? 'yearly' : 'monthly';

        return $subscription->onTrial() ? "Trial, then billed {$interval}" : 'Billed '.$interval;
    }

    /**
     * Monthly or yearly. Moving to yearly is a fresh checkout, paid there and then; going back to
     * monthly is only recorded and happens on the last day of the paid year.
     *
     * @return array<string, mixed>|null
     */
    private function switch(Workspace $workspace, $subscription, int $units): ?array
    {
        if ($workspace->canSwitchToYearly()) {
            return ['to' => 'yearly', 'units' => max(1, $units)];
        }

        if (! $workspace->canSwitchToMonthly()) {
            return null;
        }

        $renewsAt = $subscription?->renews_at;

        return [
            'to' => $workspace->switch_to_monthly_requested_at ? 'pending-monthly' : 'monthly',
            'paidUntil' => $renewsAt?->format('j F Y'),
            'switchOn' => $renewsAt?->copy()->subDay()->format('j F Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function license(): array
    {
        $expires = InstanceService::getInstanceVariable('license_expires_at');

        return [
            'active' => InstanceService::hasValidLicense(),
            'expiresOn' => $expires ? Carbon::parse($expires)->format('j F Y') : null,
            'buyUrl' => 'https://spacepad.io/purchase/self-hosted-pro',
        ];
    }
}
