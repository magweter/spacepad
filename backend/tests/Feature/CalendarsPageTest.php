<?php

use App\Enums\GoogleBookingMethod;
use App\Enums\OutlookBookingMethod;
use App\Enums\PermissionType;
use App\Models\CalDAVAccount;
use App\Models\Calendar;
use App\Models\Display;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Room;
use App\Models\User;
use App\Services\CalDAVService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['settings.is_self_hosted' => false]);
});

function calendarsUser(): User
{
    return User::factory()->active()->create();
}

function googleWorkspaceAccount(User $user, array $attributes = []): GoogleAccount
{
    return GoogleAccount::factory()->create($attributes + [
        'user_id' => $user->id,
        'workspace_id' => $user->primaryWorkspace()->id,
        'hosted_domain' => 'acme.test',
        'permission_type' => PermissionType::WRITE,
    ]);
}

test('the page lists the accounts of the workspace with their access and booking method', function () {
    $user = calendarsUser();
    OutlookAccount::where('user_id', $user->id)->update(['permission_type' => PermissionType::WRITE, 'booking_method' => OutlookBookingMethod::USER_ACCOUNT]);
    $google = googleWorkspaceAccount($user, ['permission_type' => PermissionType::READ]);
    CalDAVAccount::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'url' => 'https://dav.acme.test/remote.php/dav', 'permission_type' => PermissionType::WRITE]);

    // An account of someone else's workspace never shows.
    GoogleAccount::factory()->create(['workspace_id' => calendarsUser()->primaryWorkspace()->id]);

    $this->actingAs($user)->get(route('calendars.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendars/Index')
            ->where('shell.nav.4.active', true)
            ->has('accounts', 3)
            ->where('accounts.0.provider', 'microsoft')
            ->where('accounts.0.permission', 'write')
            ->where('accounts.0.bookingMethod.value', 'user_account')
            ->where('accounts.0.choosesBookingMethod', true)
            ->where('accounts.1.id', $google->id)
            ->where('accounts.1.providerLabel', 'Google Workspace')
            ->where('accounts.1.choosesBookingMethod', false)
            ->where('accounts.2.provider', 'caldav')
            ->where('accounts.2.host', 'dav.acme.test')
            ->where('notices', [])
            ->where('openModal', null)
            ->where('adminConsent', null));
});

test('an account in use by a display cannot be disconnected, and its room calendar is listed', function () {
    $user = calendarsUser();
    $account = OutlookAccount::where('user_id', $user->id)->first();
    $calendar = Calendar::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'outlook_account_id' => $account->id]);
    Room::factory()->create(['user_id' => $user->id, 'calendar_id' => $calendar->id, 'name' => 'Boardroom', 'email_address' => 'boardroom@acme.test']);
    Display::factory()->create(['user_id' => $user->id, 'workspace_id' => $user->primaryWorkspace()->id, 'calendar_id' => $calendar->id, 'display_name' => 'Boardroom display']);
    $spare = googleWorkspaceAccount($user);

    $this->actingAs($user)->get(route('calendars.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.canDelete', false)
            ->where('accounts.0.displays', 1)
            ->where('accounts.1.id', $spare->id)
            ->where('accounts.1.canDelete', true)
            ->where('calendars.0.name', 'Boardroom')
            ->where('calendars.0.email', 'boardroom@acme.test')
            ->where('calendars.0.source', 'Microsoft 365')
            ->where('calendars.0.displays.0.name', 'Boardroom display'));
});

test('accounts that still need a booking method or a service account get a notice', function () {
    $user = calendarsUser();
    $outlook = OutlookAccount::where('user_id', $user->id)->first();
    $outlook->update(['permission_type' => PermissionType::WRITE, 'booking_method' => null]);
    $google = googleWorkspaceAccount($user, ['booking_method' => GoogleBookingMethod::SERVICE_ACCOUNT]);

    $this->actingAs($user)->get(route('calendars.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notices', 2)
            ->where('notices.0.modal', 'microsoft-booking-method')
            ->where('notices.0.accountId', $outlook->id)
            ->where('notices.1.modal', 'service-account')
            ->where('notices.1.accountId', $google->id)
            ->where('accounts.1.needsServiceAccount', true));
});

test('a flow that ends in a choice opens its modal', function (string $flash, string $modal) {
    $user = calendarsUser();
    $google = googleWorkspaceAccount($user);
    $id = $modal === 'microsoft-booking-method' ? OutlookAccount::where('user_id', $user->id)->value('id') : $google->id;

    $this->actingAs($user)->withSession([$flash => $id])->get(route('calendars.index'))
        ->assertInertia(fn (Assert $page) => $page->where('openModal', ['type' => $modal, 'accountId' => (string) $id]));
})->with([
    ['open-google-booking-method-modal', 'google-booking-method'],
    ['open-microsoft-booking-method-modal', 'microsoft-booking-method'],
    ['open-service-account-modal', 'service-account'],
]);

test('a link from the overview opens its modal, but only for an account of this workspace', function () {
    $user = calendarsUser();
    $google = googleWorkspaceAccount($user);
    $foreign = GoogleAccount::factory()->create(['workspace_id' => calendarsUser()->primaryWorkspace()->id, 'hosted_domain' => 'other.test']);

    $this->actingAs($user)->get(route('calendars.index', ['open' => 'service-account', 'account' => $google->id]))
        ->assertInertia(fn (Assert $page) => $page->where('openModal', ['type' => 'service-account', 'accountId' => (string) $google->id]));

    $this->actingAs($user)->get(route('calendars.index', ['open' => 'service-account', 'account' => $foreign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('openModal', null));

    $this->actingAs($user)->get(route('calendars.index', ['open' => 'anything', 'account' => $google->id]))
        ->assertInertia(fn (Assert $page) => $page->where('openModal', null));
});

test('choosing admin consent shows the approval link on the calendars page', function () {
    $user = calendarsUser();
    $account = OutlookAccount::where('user_id', $user->id)->first();
    $account->update(['permission_type' => PermissionType::WRITE]);

    $this->actingAs($user)->post(route('outlook-accounts.set-booking-method'), [
        'outlook_account_id' => $account->id,
        'booking_method' => OutlookBookingMethod::ADMIN_CONSENT->value,
    ])->assertRedirect(route('calendars.index'));

    $this->get(route('calendars.index'))
        ->assertInertia(fn (Assert $page) => $page->where('adminConsent.url', fn ($url) => str_contains($url, 'adminconsent')));
});

test('choosing a service account opens the upload next', function () {
    $user = calendarsUser();
    $google = googleWorkspaceAccount($user);

    $this->actingAs($user)->post(route('google-accounts.set-booking-method'), [
        'google_account_id' => $google->id,
        'booking_method' => 'service_account',
    ])->assertRedirect(route('calendars.index'));

    $this->get(route('calendars.index'))
        ->assertInertia(fn (Assert $page) => $page->where('openModal.type', 'service-account'));
});

test('disconnecting an unused account returns to the calendars page', function () {
    $user = calendarsUser();
    $google = googleWorkspaceAccount($user);

    $this->actingAs($user)->delete(route('google-accounts.delete', $google))
        ->assertRedirect(route('calendars.index'))
        ->assertSessionHas('status');

    expect(GoogleAccount::find($google->id))->toBeNull();
});

test('the CalDAV form is an Inertia page that remembers the setup it came from', function () {
    $user = calendarsUser();

    $this->actingAs($user)->get(route('caldav-accounts.create', ['onboarding' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendars/CalDav')
            ->where('onboarding', true)
            ->where('urls.back', route('setup.first-display', ['step' => 'calendar'])));

    $this->mock(CalDAVService::class)->shouldReceive('checkConnection')->andReturn(['success' => true, 'message' => 'ok']);

    config(['settings.is_self_hosted' => true]);
    $this->post(route('caldav-accounts.store'), ['url' => 'http://192.168.1.20/remote.php/dav', 'username' => 'room', 'password' => 'secret'])
        ->assertRedirect(route('setup.first-display', ['step' => 'room']));
});

test('the CalDAV form outside the setup returns to the calendars page', function () {
    $user = calendarsUser();

    $this->actingAs($user)->get(route('caldav-accounts.create'))
        ->assertInertia(fn (Assert $page) => $page->where('onboarding', false)->where('urls.back', route('calendars.index')));

    $this->mock(CalDAVService::class)->shouldReceive('checkConnection')->andReturn(['success' => true, 'message' => 'ok']);

    config(['settings.is_self_hosted' => true]);
    $this->post(route('caldav-accounts.store'), ['url' => 'http://192.168.1.20/remote.php/dav', 'username' => 'room', 'password' => 'secret'])
        ->assertRedirect(route('calendars.index'));
});
