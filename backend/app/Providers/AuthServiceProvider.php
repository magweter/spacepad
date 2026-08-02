<?php

namespace App\Providers;

use App\Models\Board;
use App\Models\CalDAVAccount;
use App\Models\Display;
use App\Models\DisplayProfile;
use App\Models\GoogleAccount;
use App\Models\OutlookAccount;
use App\Models\Workspace;
use App\Policies\BoardPolicy;
use App\Policies\CalDAVAccountPolicy;
use App\Policies\DisplayPolicy;
use App\Policies\DisplayProfilePolicy;
use App\Policies\GoogleAccountPolicy;
use App\Policies\OutlookAccountPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Display::class => DisplayPolicy::class,
        Board::class => BoardPolicy::class,
        DisplayProfile::class => DisplayProfilePolicy::class,
        GoogleAccount::class => GoogleAccountPolicy::class,
        OutlookAccount::class => OutlookAccountPolicy::class,
        CalDAVAccount::class => CalDAVAccountPolicy::class,
        Workspace::class => WorkspacePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
