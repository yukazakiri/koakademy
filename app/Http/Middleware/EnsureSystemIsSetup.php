<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureSystemIsSetup
{
    private const string CACHE_KEY = 'system_is_setup';

    private const int CACHE_TTL_SECONDS = 300;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        // Skip setup routes, container health/build probes, and ignition/dev routes
        if ($request->is('setup') || $request->is('setup/*') || $request->is('health') || $request->is('up') || $request->is('build-version') || $request->is('_ignition/*') || $request->is('livewire/*')) {
            return $next($request);
        }

        if (Cache::get(self::CACHE_KEY) === true) {
            return $next($request);
        }

        try {
            $hasCoreData = $this->hasCoreSetupData();
            $setupCompleted = GeneralSetting::query()->where('is_setup', true)->exists();

            if ($setupCompleted && ! $hasCoreData) {
                GeneralSetting::query()->where('is_setup', true)->update(['is_setup' => false]);
                $setupCompleted = false;
            }

            if ($setupCompleted || $hasCoreData) {
                Cache::put(self::CACHE_KEY, true, self::CACHE_TTL_SECONDS);

                return $next($request);
            }

            if (User::where('role', UserRole::SuperAdmin)->exists()) {
                Cache::put(self::CACHE_KEY, true, self::CACHE_TTL_SECONDS);

                return $next($request);
            }
        } catch (Throwable) {
            // DB not ready or migrated, means not setup
        }

        return redirect()->route('setup.show');
    }

    private function hasCoreSetupData(): bool
    {
        if (User::query()->exists()) {
            return true;
        }

        return School::query()->exists();
    }
}
