<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminMergeController;
use App\Http\Controllers\AdminRoadmapController;
use App\Http\Controllers\AdminWorkspaceController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CalDAVAccountsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\DisplayDiagnosticsController;
use App\Http\Controllers\DisplaySettingsController;
use App\Http\Controllers\GoogleAccountsController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OutlookAccountsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilesController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceInvitationController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

// Public board routes (no authentication required)
Route::middleware('throttle:public_tokens')->group(function () {
    Route::get('/b/{token}', [BoardController::class, 'public'])->name('boards.public');
    Route::get('/b/{token}/logo', [BoardController::class, 'servePublicLogo'])->name('boards.public.logo');
});

// Accepting a workspace invitation. Deliberately outside the auth group: the token is the
// authentication. Also outside 'user.active', so a brand-new invitee is not bounced to
// onboarding before they have joined anything.
Route::middleware('throttle:invitations')->group(function () {
    Route::get('/invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [AcceptInvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('/invitations/{token}/decline', [AcceptInvitationController::class, 'decline'])->name('invitations.decline');
    Route::post('/invitations/{token}/switch-account', [AcceptInvitationController::class, 'switchAccount'])->name('invitations.switch-account');
});

Route::get('/login', [LoginController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

Route::get('/register', [RegisterController::class, 'create'])
    ->middleware('guest')
    ->name('register');

Route::post('/register', [RegisterController::class, 'store'])
    ->middleware('guest')
    ->name('register.store');

Route::post('/register/resend', [RegisterController::class, 'resend'])
    ->middleware('guest')
    ->name('register.resend');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('auth')->group(function () {
    Route::get('/microsoft/redirect', [MicrosoftController::class, 'redirect'])->name('auth.microsoft.redirect');
    Route::get('/microsoft/callback', [MicrosoftController::class, 'callback']);
    Route::get('/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/google/callback', [GoogleController::class, 'callback']);
});

// Outlook OAuth callback — must be outside the auth middleware so that the
// Microsoft admin consent redirect works even when the admin has no Spacepad
// session. The controller guards the regular OAuth code path itself.
Route::get('/outlook-accounts/callback', [OutlookAccountsController::class, 'callback']);

Route::middleware(['auth', 'user.update-last-activity', 'gtm'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard')->middleware('user.active');
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding')->middleware('user.onboarding');
    Route::post('/onboarding/usage-type', [OnboardingController::class, 'updateUsageType'])->name('onboarding.usage-type');
    Route::post('/onboarding/terms', [OnboardingController::class, 'acceptTerms'])->name('onboarding.terms');
    Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');

    Route::post('/outlook-accounts/auth', [OutlookAccountsController::class, 'auth'])->name('outlook-accounts.auth');
    Route::post('/outlook-accounts/booking-method', [OutlookAccountsController::class, 'setBookingMethod'])->name('outlook-accounts.set-booking-method');
    Route::delete('/outlook-accounts/{outlookAccount}', [OutlookAccountsController::class, 'delete'])->name('outlook-accounts.delete');

    Route::post('/google-accounts/booking-method', [GoogleAccountsController::class, 'setBookingMethod'])->name('google-accounts.set-booking-method');
    Route::post('/google-accounts/auth', [GoogleAccountsController::class, 'auth'])->name('google-accounts.auth');
    Route::post('/google-accounts/service-account', [GoogleAccountsController::class, 'uploadServiceAccount'])->name('google-accounts.service-account');
    Route::get('/google-accounts/callback', [GoogleAccountsController::class, 'callback']);
    Route::delete('/google-accounts/{googleAccount}', [GoogleAccountsController::class, 'delete'])->name('google-accounts.delete');

    Route::get('/caldav-accounts/create', [CalDAVAccountsController::class, 'create'])->name('caldav-accounts.create');
    Route::post('/caldav-accounts', [CalDAVAccountsController::class, 'store'])->name('caldav-accounts.store');
    Route::delete('/caldav-accounts/{caldavAccount}', [CalDAVAccountsController::class, 'delete'])->name('caldav-accounts.delete');

    Route::get('/displays/create', [DisplayController::class, 'create'])
        ->name('displays.create');
    Route::post('/displays', [DisplayController::class, 'store'])->name('displays.store');
    Route::patch('/displays/{display}/status', [DisplayController::class, 'updateStatus'])
        ->name('displays.updateStatus');
    Route::delete('/displays/{display}', [DisplayController::class, 'delete'])->name('displays.delete');

    // Display configuration: one screen, saved per section
    Route::get('/displays/{display}/configure', [DisplaySettingsController::class, 'configure'])
        ->name('displays.configure');
    Route::put('/displays/{display}/configure/{section}', [DisplaySettingsController::class, 'updateSection'])
        ->name('displays.section.update');
    Route::post('/displays/{display}/configure/{section}/reset', [DisplaySettingsController::class, 'resetSection'])
        ->name('displays.section.reset');
    Route::put('/displays/{display}/profile', [DisplaySettingsController::class, 'updateProfile'])
        ->name('displays.profile.update');
    Route::post('/displays/{display}/settings/reset-to-profile', [DisplaySettingsController::class, 'resetToProfile'])
        ->name('displays.settings.reset-to-profile');
    Route::post('/displays/profile/bulk', [DisplaySettingsController::class, 'bulkAssignProfile'])
        ->name('displays.profile.bulk');

    // The settings and customization screens were merged into the configuration screen above; keep
    // the old URLs working for bookmarks and links in earlier e-mails.
    Route::get('/displays/{display}/settings', fn (string $display) => redirect()->route('displays.configure', $display))
        ->name('displays.settings.index');
    Route::get('/displays/{display}/customization', fn (string $display) => redirect()->route('displays.configure', $display))
        ->name('displays.customization');

    Route::get('/calendars/outlook/{id}', [CalendarController::class, 'outlook'])
        ->name('calendars.outlook');
    Route::get('/calendars/google/{id}', [CalendarController::class, 'google'])
        ->name('calendars.google');
    Route::get('/calendars/caldav/{id}', [CalendarController::class, 'caldav'])
        ->name('calendars.caldav');
    Route::get('/rooms/outlook/{id}', [RoomController::class, 'outlook'])
        ->name('rooms.outlook');
    Route::get('/rooms/google/{id}', [RoomController::class, 'google'])
        ->name('rooms.google');

    Route::post('/license/validate', [LicenseController::class, 'validateLicense'])->name('license.validate');

    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

    // Literal /workspaces/... paths must stay above any /workspaces/{workspace} route.
    Route::get('/workspaces/members', [WorkspaceMemberController::class, 'index'])->name('workspaces.members');
    Route::patch('/workspaces/members/{member}', [WorkspaceMemberController::class, 'update'])->name('workspaces.members.update');
    Route::delete('/workspaces/members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy');
    Route::post('/workspaces/leave', [WorkspaceMemberController::class, 'leave'])->name('workspaces.leave');

    Route::post('/workspaces/invitations', [WorkspaceInvitationController::class, 'store'])
        ->middleware('throttle:workspace_invites')->name('workspaces.invitations.store');
    Route::post('/workspaces/invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])
        ->middleware('throttle:workspace_invites')->name('workspaces.invitations.resend');
    Route::delete('/workspaces/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])
        ->name('workspaces.invitations.destroy');

    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/thanks', [BillingController::class, 'thanks'])->name('billing.thanks');

    Route::get('/account', [ProfileController::class, 'show'])->name('profile.show');
    Route::delete('/account', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/admin/users/{user}', [AdminController::class, 'showUser'])->name('admin.users.show');
    Route::delete('/admin/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    // Billing is set on the workspace, which is what holds the subscription and the usage.
    Route::get('/admin/workspaces', [AdminWorkspaceController::class, 'index'])->name('admin.workspaces.index');
    Route::get('/admin/workspaces/{workspace}', [AdminWorkspaceController::class, 'show'])->name('admin.workspaces.show');
    Route::post('/admin/workspaces/{workspace}/billing', [AdminWorkspaceController::class, 'updateBilling'])
        ->name('admin.workspaces.billing');
    Route::post('/admin/users/{user}/impersonate', [AdminController::class, 'impersonate'])->name('admin.users.impersonate');
    Route::post('/admin/stop-impersonating', [AdminController::class, 'stopImpersonating'])->name('admin.stop-impersonating');

    Route::get('/admin/merge', [AdminMergeController::class, 'index'])->name('admin.merge.index');
    Route::post('/admin/merge/preview', [AdminMergeController::class, 'preview'])->name('admin.merge.preview');
    Route::post('/admin/merge', [AdminMergeController::class, 'store'])->name('admin.merge.store');

    // Display image serving route
    Route::get('/displays/{display}/images/{type}', [DisplaySettingsController::class, 'serveImage'])
        ->name('displays.images');

    // Diagnostics — modal, run endpoint only (index redirects for old bookmarks)
    Route::redirect('/diagnostics', '/')->name('diagnostics.index');
    Route::get('/displays/{display}/diagnostics/run', [DisplayDiagnosticsController::class, 'run'])
        ->name('displays.diagnostics.run');
    Route::post('/displays/{display}/diagnostics/reset-account', [DisplayDiagnosticsController::class, 'resetAccount'])
        ->name('displays.diagnostics.reset-account');

    // Display profiles (reusable settings/theme sets)
    Route::get('/profiles', [ProfilesController::class, 'index'])->name('profiles.index');
    Route::get('/profiles/create', [ProfilesController::class, 'create'])->name('profiles.create');
    Route::post('/profiles', [ProfilesController::class, 'store'])->name('profiles.store');
    Route::get('/profiles/{profile}/edit', [ProfilesController::class, 'edit'])->name('profiles.edit');
    Route::put('/profiles/{profile}', [ProfilesController::class, 'update'])->name('profiles.update');
    Route::delete('/profiles/{profile}', [ProfilesController::class, 'destroy'])->name('profiles.destroy');
    Route::get('/profiles/{profile}/images/{type}', [ProfilesController::class, 'serveImage'])->name('profiles.images');

    // Boards routes
    Route::get('/boards/create', [BoardController::class, 'create'])->name('boards.create');
    Route::post('/boards', [BoardController::class, 'store'])->name('boards.store');
    Route::get('/boards/{board}', [BoardController::class, 'show'])->name('boards.show');
    Route::get('/boards/{board}/edit', [BoardController::class, 'edit'])->name('boards.edit');
    Route::put('/boards/{board}', [BoardController::class, 'update'])->name('boards.update');
    Route::delete('/boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
    Route::get('/boards/{board}/images/logo', [BoardController::class, 'serveLogo'])->name('boards.images.logo');

    // Support / FAQ
    Route::post('/support/ask', [SupportController::class, 'store'])->name('support.ask');

    // Roadmap
    Route::post('/roadmap/{roadmapItem}/vote', [RoadmapController::class, 'vote'])->name('roadmap.vote');
    Route::post('/roadmap/suggest', [RoadmapController::class, 'suggest'])->name('roadmap.suggest');

    // Admin — Roadmap
    Route::get('/admin/roadmap', [AdminRoadmapController::class, 'index'])->name('admin.roadmap.index');
    Route::get('/admin/roadmap/create', [AdminRoadmapController::class, 'create'])->name('admin.roadmap.create');
    Route::post('/admin/roadmap', [AdminRoadmapController::class, 'store'])->name('admin.roadmap.store');
    Route::get('/admin/roadmap/{roadmapItem}/edit', [AdminRoadmapController::class, 'edit'])->name('admin.roadmap.edit');
    Route::put('/admin/roadmap/{roadmapItem}', [AdminRoadmapController::class, 'update'])->name('admin.roadmap.update');
    Route::post('/admin/roadmap/{roadmapItem}/approve', [AdminRoadmapController::class, 'approve'])->name('admin.roadmap.approve');
    Route::delete('/admin/roadmap/{roadmapItem}', [AdminRoadmapController::class, 'destroy'])->name('admin.roadmap.destroy');
});
