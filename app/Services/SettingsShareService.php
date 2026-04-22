<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Settings\SiteSettings;
use App\Settings\SocialMediaSettings;
use Illuminate\Http\Request;

final readonly class SettingsShareService
{
    public function __construct(
        private SiteSettings $siteSettings,
        private SocialMediaSettings $socialMediaSettings,
        private GeneralSettingsService $generalSettingsService
    ) {}

    /**
     * Get social media settings for frontend.
     */
    public function getSocialMediaSettings(): SocialMediaSettings
    {
        return $this->socialMediaSettings;
    }

    /**
     * Get site settings for frontend.
     */
    public function getSiteSettings(): SiteSettings
    {
        return $this->siteSettings;
    }

    /**
     * Get branding array from site settings.
     */
    public function getBranding(): array
    {
        return $this->siteSettings->getBrandingArray();
    }

    /**
     * Get the grading system configuration for frontend consumers.
     *
     * @return array<string, mixed>
     */
    public function getGrading(): array
    {
        return app(GradingSystemService::class)->getConfig();
    }

    /**
     * Get app name based on current domain.
     */
    public function getAppName(Request $request): string
    {
        $isPortalDomain = $this->isPortalDomain($request);

        return $isPortalDomain
            ? ($this->siteSettings->portal_name ?: $this->siteSettings->getAppName())
            : $this->siteSettings->getAppName();
    }

    /**
     * Check if current domain is portal domain.
     */
    public function isPortalDomain(Request $request): bool
    {
        $currentHost = mb_strtolower($request->getHost());
        $portalHost = $this->normalizeHost((string) config('app.portal_host', 'portal.koakademy.test'));

        if ($portalHost === '') {
            return false;
        }

        return $currentHost === $portalHost;
    }

    /**
     * Get settings data for frontend.
     */
    public function getSettings(): array
    {
        $activeSchoolId = $this->generalSettingsService->getActiveSchoolId();

        return [
            'currentSemester' => $this->generalSettingsService->getCurrentSemester(),
            'currentSchoolYear' => $this->generalSettingsService->getCurrentSchoolYearStart(),
            'systemSemester' => $this->generalSettingsService->getSystemDefaultSemester(),
            'systemSchoolYear' => $this->generalSettingsService->getSystemDefaultSchoolYearStart(),
            'availableSemesters' => $this->generalSettingsService->getAvailableSemesters(),
            'availableSchoolYears' => $this->generalSettingsService->getAvailableSchoolYears(),
            'activeSchoolId' => $activeSchoolId,
            'availableSchools' => \App\Models\School::all(['id', 'name', 'code'])->toArray(),
        ];
    }

    /**
     * Get auth data for frontend.
     *
     * @return array<string, mixed>
     */
    public function getAuthData(?User $user): array
    {
        return [
            'user' => $user instanceof User ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role instanceof \App\Enums\UserRole ? $user->role->value : $user->role,
                'avatar' => $user->avatar_url,
                'permissions' => $this->getUserPermissions($user),
            ] : null,
            'isImpersonating' => session()->has('impersonator_id'),
        ];
    }

    /**
     * Get user permissions from both direct assignments and Spatie role matching user's enum role.
     *
     * @return array<int, string>
     */
    private function getUserPermissions(User $user): array
    {
        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        if (count($permissions) > 0) {
            return $permissions;
        }

        $userRoleValue = $user->role instanceof \App\Enums\UserRole
            ? $user->role->value
            : $user->role;

        if (is_string($userRoleValue) && $userRoleValue !== '') {
            $userRole = \App\Enums\UserRole::tryFrom($userRoleValue);

            /** @var \Spatie\Permission\Models\Role|null $spatieRole */
            $spatieRole = \Spatie\Permission\Models\Role::query()
                ->where('name', $userRoleValue)
                ->first();

            if (! $spatieRole && $userRole instanceof \App\Enums\UserRole) {
                $spatieRole = \Spatie\Permission\Models\Role::query()
                    ->where('name', (string) $userRole->getLabel())
                    ->first();
            }

            if ($spatieRole) {
                $permissions = $spatieRole->permissions->pluck('name')->values()->all();
            }
        }

        return $permissions;
    }

    private function normalizeHost(string $host): string
    {
        $trimmedHost = mb_trim($host);

        if ($trimmedHost === '') {
            return '';
        }

        $parsedHost = parse_url($trimmedHost, PHP_URL_HOST);

        return mb_strtolower(is_string($parsedHost) ? $parsedHost : $trimmedHost);
    }
}
