<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureSystemIsSetup
{
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

        // Skip setup routes and ignition/dev routes
        if ($request->is('setup') || $request->is('setup/*') || $request->is('_ignition/*') || $request->is('livewire/*')) {
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
                return $next($request);
            }

            if (User::where('role', UserRole::SuperAdmin)->exists()) {
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
