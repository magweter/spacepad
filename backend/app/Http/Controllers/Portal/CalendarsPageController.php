<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AccountStatus;
use App\Enums\GoogleBookingMethod;
use App\Enums\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\CalDAVAccount;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Workspace;
use App\Support\PortalShell;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The calendar accounts a workspace reads its rooms from, the room calendars its displays use and
 * the choices an account still needs before booking from a display works.
 *
 * Flows that end in a choice (a booking method after connecting, the service account upload, the
 * Microsoft admin consent link) land here with a session flash; the overview links its notices
 * with ?open=...&account=... instead. Both come out as one `openModal` prop.
 */
class CalendarsPageController extends Controller
{
    /** Session flashes and query values that open a modal, mapped to the modal's name. */
    private const MODAL_FLASHES = [
        'open-google-booking-method-modal' => 'google-booking-method',
        'open-microsoft-booking-method-modal' => 'microsoft-booking-method',
        'open-service-account-modal' => 'service-account',
    ];

    public function index(Request $request): Response
    {
        $workspace = $request->user()->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        $outlook = OutlookAccount::where('workspace_id', $workspace->id)->with('calendars.displays', 'calendars.room')->orderBy('created_at')->get();
        $google = GoogleAccount::where('workspace_id', $workspace->id)->with('calendars.displays', 'calendars.room')->orderBy('created_at')->get();
        $caldav = CalDAVAccount::where('workspace_id', $workspace->id)->with('calendars.displays', 'calendars.room')->orderBy('created_at')->get();

        $accounts = collect()
            ->merge($outlook->map(fn (OutlookAccount $a) => $this->account($request, $a, 'microsoft')))
            ->merge($google->map(fn (GoogleAccount $a) => $this->account($request, $a, 'google')))
            ->merge($caldav->map(fn (CalDAVAccount $a) => $this->account($request, $a, 'caldav')))
            ->values();

        $calendars = collect()
            ->merge($outlook->flatMap(fn ($a) => $a->calendars->map(fn ($c) => $this->calendar($c, 'Microsoft 365'))))
            ->merge($google->flatMap(fn ($a) => $a->calendars->map(fn ($c) => $this->calendar($c, 'Google Workspace'))))
            ->merge($caldav->flatMap(fn ($a) => $a->calendars->map(fn ($c) => $this->calendar($c, 'CalDAV'))))
            ->sortBy(fn ($c) => mb_strtolower($c['name']))
            ->values();

        return Inertia::render('Calendars/Index', [
            'shell' => PortalShell::for($request, $workspace, 'calendars'),
            'accounts' => $accounts->all(),
            'calendars' => $calendars->all(),
            'notices' => $this->notices($accounts),
            'openModal' => $this->openModal($request, $accounts),
            'adminConsent' => $request->session()->get('needs_admin_consent') ? [
                'url' => (string) $request->session()->get('admin_consent_url', ''),
            ] : null,
            'urls' => [
                'outlookAuth' => route('outlook-accounts.auth'),
                'googleAuth' => route('google-accounts.auth'),
                'caldavCreate' => route('caldav-accounts.create'),
                'microsoftBookingMethod' => route('outlook-accounts.set-booking-method'),
                'googleBookingMethod' => route('google-accounts.set-booking-method'),
                'serviceAccount' => route('google-accounts.service-account'),
                'newDisplay' => $this->newDisplayUrl($workspace),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function account(Request $request, OutlookAccount|GoogleAccount|CalDAVAccount $account, string $provider): array
    {
        $write = $account->permission_type === PermissionType::WRITE;
        $business = $provider !== 'caldav' && $account->isBusiness();
        $method = $provider !== 'caldav' ? $account->booking_method : null;

        // Only a business account that may book has a choice to make; a personal Google account
        // always books as itself.
        $choosesMethod = $write && $business;
        $usesServiceAccount = $provider === 'google' && $business && $method === GoogleBookingMethod::SERVICE_ACCOUNT;
        $inUse = $account->calendars->isNotEmpty();

        return [
            'id' => $account->id,
            'provider' => $provider,
            'providerLabel' => match ($provider) {
                'microsoft' => 'Microsoft 365',
                'google' => $business ? 'Google Workspace' : 'Google',
                default => 'CalDAV',
            },
            'name' => $account->name,
            'email' => $account->email,
            'host' => $provider === 'caldav' ? parse_url((string) $account->url, PHP_URL_HOST) : null,
            'permission' => $account->permission_type?->value,
            'business' => $business,
            'status' => $account->status === AccountStatus::ERROR ? 'error' : 'connected',
            'bookingMethod' => $method ? ['value' => $method->value, 'label' => $method->label(), 'description' => $method->description()] : null,
            'choosesBookingMethod' => $choosesMethod,
            'needsBookingMethod' => $choosesMethod && $method === null,
            'usesServiceAccount' => $usesServiceAccount,
            'needsServiceAccount' => $usesServiceAccount && ! $account->service_account_file_path,
            'calendars' => $account->calendars->count(),
            'displays' => $account->calendars->sum(fn (Calendar $c) => $c->displays->count()),
            'canDelete' => ! $inUse && $request->user()->can('delete', $account),
            'deleteUrl' => match ($provider) {
                'microsoft' => route('outlook-accounts.delete', $account),
                'google' => route('google-accounts.delete', $account),
                default => route('caldav-accounts.delete', $account),
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calendar(Calendar $calendar, string $source): array
    {
        return [
            'id' => $calendar->id,
            'name' => $calendar->room?->name ?: $calendar->name ?: 'Calendar',
            'email' => $calendar->room?->email_address,
            'source' => $source,
            'displays' => $calendar->displays->sortBy('name')->map(fn (Display $d) => [
                'name' => $d->display_name ?: $d->name,
                'url' => route('displays.configure', $d),
            ])->values()->all(),
        ];
    }

    /**
     * The same "action needed" notices the overview shows, opening the modal on this page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function notices(Collection $accounts): array
    {
        return $accounts->flatMap(function (array $account) {
            if ($account['status'] === 'error') {
                return [[
                    'tone' => 'danger', 'tag' => 'Action required',
                    'title' => "Sign in again to {$account['email']}",
                    'body' => 'The sign-in expired or its permissions changed, so its rooms show no meetings until it is connected again.',
                    'cta' => 'Sign in again',
                    'modal' => 'reconnect',
                    'accountId' => $account['id'],
                ]];
            }

            if ($account['needsBookingMethod']) {
                return [[
                    'title' => "Choose how {$account['email']} books rooms",
                    'body' => 'Booking from a display waits for this choice.',
                    'cta' => 'Choose',
                    'modal' => $account['provider'] === 'google' ? 'google-booking-method' : 'microsoft-booking-method',
                    'accountId' => $account['id'],
                ]];
            }

            if ($account['needsServiceAccount']) {
                return [[
                    'title' => "Upload the service account for {$account['email']}",
                    'body' => 'Booking from a display needs the service account key.',
                    'cta' => 'Upload',
                    'modal' => 'service-account',
                    'accountId' => $account['id'],
                ]];
            }

            return [];
        })->values()->all();
    }

    /**
     * The modal to open on arrival: a flash from a flow that just ended wins over the query.
     *
     * @return array{type: string, accountId: string}|null
     */
    private function openModal(Request $request, Collection $accounts): ?array
    {
        $type = null;
        $accountId = null;

        foreach (self::MODAL_FLASHES as $flash => $modal) {
            if ($request->session()->has($flash)) {
                [$type, $accountId] = [$modal, (string) $request->session()->get($flash)];
                break;
            }
        }

        // From the overview's "Action required" notice for an account in error: sign in to that account again.
        if ($type === null && $request->query('open') === 'reconnect') {
            $accountId = (string) $request->query('account');

            return $accounts->contains(fn ($a) => (string) $a['id'] === $accountId) ? ['type' => 'reconnect', 'accountId' => $accountId] : null;
        }

        if ($type === null && in_array($request->query('open'), self::MODAL_FLASHES, true)) {
            [$type, $accountId] = [$request->query('open'), (string) $request->query('account')];
        }

        if ($type === null) {
            return null;
        }

        // Only an account of this workspace, and of the provider the modal is for.
        $provider = $type === 'microsoft-booking-method' ? 'microsoft' : 'google';
        $known = $accounts->contains(fn ($a) => (string) $a['id'] === $accountId && $a['provider'] === $provider);

        return $known ? ['type' => $type, 'accountId' => $accountId] : null;
    }

    private function newDisplayUrl(Workspace $workspace): string
    {
        return $workspace->displays()->exists()
            ? route('setup.first-display', ['step' => 'room', 'new' => 1])
            : route('setup.first-display');
    }
}
