<?php

namespace App\Providers;

use App\Models\Display;
use App\Models\Board;
use App\Policies\DisplayPolicy;
use App\Policies\BoardPolicy;
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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
} 