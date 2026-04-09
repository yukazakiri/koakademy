<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Account;
use App\Policies\AccountPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Account::class => AccountPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define gates for account management
        Gate::define('view-any-accounts', fn ($user) => $user->is_admin ?? false);
        Gate::define('view-accounts', fn ($user) => $user->is_admin ?? false);
        Gate::define('create-accounts', fn ($user) => $user->is_admin ?? false);
        Gate::define('update-accounts', fn ($user) => $user->is_admin ?? false);
        Gate::define('delete-accounts', fn ($user) => $user->is_admin ?? false);
    }
}
