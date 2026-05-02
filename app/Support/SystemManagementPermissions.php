<?php

declare(strict_types=1);

namespace App\Support;

final class SystemManagementPermissions
{
    /**
     * @return array<string, array{view: string, update: string|null}>
     */
    public static function definitions(): array
    {
        return [
            'school' => [
                'view' => 'View:SystemManagementSchool',
                'update' => 'Update:SystemManagementSchool',
            ],
            'pipeline' => [
                'view' => 'View:SystemManagementEnrollmentPipeline',
                'update' => 'Update:SystemManagementEnrollmentPipeline',
            ],
            'seo' => [
                'view' => 'View:SystemManagementSeo',
                'update' => 'Update:SystemManagementSeo',
            ],
            'analytics' => [
                'view' => 'View:SystemManagementAnalytics',
                'update' => 'Update:SystemManagementAnalytics',
            ],
            'brand' => [
                'view' => 'View:SystemManagementBrand',
                'update' => 'Update:SystemManagementBrand',
            ],
            'socialite' => [
                'view' => 'View:SystemManagementSocialite',
                'update' => 'Update:SystemManagementSocialite',
            ],
            'mail' => [
                'view' => 'View:SystemManagementMail',
                'update' => 'Update:SystemManagementMail',
            ],
            'api' => [
                'view' => 'View:SystemManagementApi',
                'update' => 'Update:SystemManagementApi',
            ],
            'notifications' => [
                'view' => 'View:SystemManagementNotifications',
                'update' => 'Update:SystemManagementNotifications',
            ],
            'grading' => [
                'view' => 'View:SystemManagementGrading',
                'update' => 'Update:SystemManagementGrading',
            ],
            'pulse' => [
                'view' => 'View:SystemManagementPulse',
                'update' => null,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function sectionKeys(): array
    {
        return array_keys(self::definitions());
    }

    public static function viewPermission(string $section): string
    {
        return self::definitions()[$section]['view'];
    }

    public static function updatePermission(string $section): ?string
    {
        return self::definitions()[$section]['update'];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return collect(self::definitions())
            ->flatMap(fn (array $definition): array => array_filter([$definition['view'], $definition['update']]))
            ->values()
            ->all();
    }
}
