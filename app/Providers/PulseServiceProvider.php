<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Pulse;

final class PulseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(Pulse::class)->user(fn ($user): array => [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
        ]);

        Gate::define('viewPulse', function (mixed $user = null): bool {
            if (! $user instanceof User) {
                return false;
            }

            if (in_array($user->role, [UserRole::SuperAdmin, UserRole::Developer], true)) {
                return true;
            }

            $superAdminRole = (string) config('filament-shield.super_admin.name', UserRole::SuperAdmin->value);

            return $user->hasAnyRole([$superAdminRole, UserRole::Developer->value]);
        });
    }
}
