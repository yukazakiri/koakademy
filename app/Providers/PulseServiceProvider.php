<?php

declare(strict_types=1);

namespace App\Providers;

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

        Gate::define('viewPulse', fn ($user): bool =>
            // Only allow users with admin email or specific roles to view Pulse
            $user->email === 'superadminh@gmail.com' || $user->email === env('ADMIN_EMAIL') || $user->hasRole('super_admin'));
    }
}
