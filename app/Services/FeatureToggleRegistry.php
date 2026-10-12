<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Features\Contracts\FeatureToggle;
use App\Features\Toggles\AdminDeveloperMode;
use App\Features\Toggles\AiFacultyAssistant;
use App\Features\Toggles\AiFinanceAssistant;
use App\Features\Toggles\AiHelpDeskAssistant;
use App\Features\Toggles\AiRegistrarAuditor;
use App\Features\Toggles\AiStudentAdvisor;
use App\Features\Toggles\FacultyActionCenter;
use App\Features\Toggles\FacultyAnnouncements;
use App\Features\Toggles\FacultyAssessments;
use App\Features\Toggles\FacultyAtRiskAlerts;
use App\Features\Toggles\FacultyAttendance;
use App\Features\Toggles\FacultyClasses;
use App\Features\Toggles\FacultyDashboard;
use App\Features\Toggles\FacultyDeveloperMode;
use App\Features\Toggles\FacultyForms;
use App\Features\Toggles\FacultyGrades;
use App\Features\Toggles\FacultyHelp;
use App\Features\Toggles\FacultyInbox;
use App\Features\Toggles\FacultyInsights;
use App\Features\Toggles\FacultyOfficeHours;
use App\Features\Toggles\FacultyRequestsApprovals;
use App\Features\Toggles\FacultyResources;
use App\Features\Toggles\FacultySchedule;
use App\Features\Toggles\FacultySettings;
use App\Features\Toggles\FacultyToolkit;
use App\Features\Toggles\OnlineCollegeEnrollment;
use App\Features\Toggles\OnlineTesdaEnrollment;
use App\Features\Toggles\StudentAnnouncements;
use App\Features\Toggles\StudentAttendanceTracker;
use App\Features\Toggles\StudentAvatarUpload;
use App\Features\Toggles\StudentClasses;
use App\Features\Toggles\StudentDashboard;
use App\Features\Toggles\StudentDeveloperMode;
use App\Features\Toggles\StudentGradesPreview;
use App\Features\Toggles\StudentHelp;
use App\Features\Toggles\StudentInformationUpdates;
use App\Features\Toggles\StudentSchedule;
use App\Features\Toggles\StudentSettings;
use App\Features\Toggles\StudentSignaturePad;
use App\Features\Toggles\StudentTuition;

/**
 * Registry mapping feature keys to their Pennant feature class names.
 * All class-based features must be registered here.
 */
final class FeatureToggleRegistry
{
    /**
     * Map of feature_key => FeatureToggle class name.
     *
     * @var array<string, class-string<FeatureToggle>>
     */
    private const array KEY_TO_CLASS = [
        // Faculty features
        'faculty-dashboard' => FacultyDashboard::class,
        'faculty-action-center' => FacultyActionCenter::class,
        'faculty-classes' => FacultyClasses::class,
        'faculty-schedule' => FacultySchedule::class,
        'faculty-announcements' => FacultyAnnouncements::class,
        'faculty-settings' => FacultySettings::class,
        'faculty-help' => FacultyHelp::class,
        'faculty-toolkit' => FacultyToolkit::class,
        'faculty-at-risk-alerts' => FacultyAtRiskAlerts::class,
        'faculty-assessments' => FacultyAssessments::class,
        'faculty-inbox' => FacultyInbox::class,
        'faculty-office-hours' => FacultyOfficeHours::class,
        'faculty-requests-approvals' => FacultyRequestsApprovals::class,
        'faculty-insights' => FacultyInsights::class,
        'faculty-grades' => FacultyGrades::class,
        'faculty-attendance' => FacultyAttendance::class,
        'faculty-resources' => FacultyResources::class,
        'faculty-forms' => FacultyForms::class,
        'faculty-developer-mode' => FacultyDeveloperMode::class,

        // Admin features
        'admin-developer-mode' => AdminDeveloperMode::class,

        // Student features
        'student-dashboard' => StudentDashboard::class,
        'student-classes' => StudentClasses::class,
        'student-tuition' => StudentTuition::class,
        'student-schedule' => StudentSchedule::class,
        'student-announcements' => StudentAnnouncements::class,
        'student-settings' => StudentSettings::class,
        'student-information-updates' => StudentInformationUpdates::class,
        'student-help' => StudentHelp::class,
        'student-grades-preview' => StudentGradesPreview::class,
        'student-attendance-tracker' => StudentAttendanceTracker::class,
        'student-developer-mode' => StudentDeveloperMode::class,

        // Generic features
        'student-signature-pad' => StudentSignaturePad::class,
        'student-avatar-upload' => StudentAvatarUpload::class,
        'online-college-enrollment' => OnlineCollegeEnrollment::class,
        'online-tesda-enrollment' => OnlineTesdaEnrollment::class,

        // AI features
        'ai-student-advisor' => AiStudentAdvisor::class,
        'ai-faculty-assistant' => AiFacultyAssistant::class,
        'ai-registrar-auditor' => AiRegistrarAuditor::class,
        'ai-finance-assistant' => AiFinanceAssistant::class,
        'ai-help-desk-assistant' => AiHelpDeskAssistant::class,
    ];

    /**
     * Get the class name for a feature key.
     *
     * @return class-string<FeatureToggle>|null
     */
    public static function classForKey(string $key): ?string
    {
        return self::KEY_TO_CLASS[self::canonicalKey($key)] ?? null;
    }

    public static function canonicalKey(string $key): string
    {
        return str_starts_with($key, 'onboarding-') ? mb_substr($key, 11) : $key;
    }

    /** @return array{roles: list<string>, default_enabled: bool, tier: string, opt_in: bool, public: bool} */
    public static function definition(string $key): array
    {
        $key = self::canonicalKey($key);
        $public = in_array($key, ['online-college-enrollment', 'online-tesda-enrollment'], true);
        $roles = array_values(array_map(fn (UserRole $role): string => $role->value,
            array_filter(UserRole::cases(), static function (UserRole $role) use ($key, $public): bool {
                return match (true) {
                    $public => true,
                    str_starts_with($key, 'student-'), $key === 'ai-student-advisor' => $role->isStudent(),
                    str_starts_with($key, 'faculty-'), $key === 'ai-faculty-assistant' => $role->isFaculty(),
                    $key === 'admin-developer-mode', $key === 'ai-registrar-auditor' => $role->canAccessAdminPortal(),
                    $key === 'ai-finance-assistant' => $role->canAccessAdminPortal() || $role->isStudent(),
                    $key === 'ai-help-desk-assistant' => true,
                    default => false,
                };
            })));
        $experimental = in_array($key, [
            'student-grades-preview', 'student-attendance-tracker', 'faculty-at-risk-alerts',
            'faculty-assessments', 'faculty-inbox', 'faculty-office-hours',
            'faculty-requests-approvals', 'faculty-insights', 'faculty-forms',
        ], true);
        $privileged = str_ends_with($key, '-developer-mode') || str_starts_with($key, 'ai-');

        return [
            'roles' => $roles, 'default_enabled' => ! $experimental && ! $privileged,
            'tier' => $privileged ? 'restricted' : ($experimental ? 'beta' : 'built_in'),
            'opt_in' => $experimental, 'public' => $public,
        ];
    }

    /**
     * Get the feature key for a class name.
     */
    public static function keyForClass(string $class): ?string
    {
        $flipped = array_flip(self::KEY_TO_CLASS);

        return $flipped[$class] ?? null;
    }

    /**
     * Get all registered feature keys.
     *
     * @return array<int, string>
     */
    public static function allKeys(): array
    {
        return array_keys(self::KEY_TO_CLASS);
    }

    /**
     * Flush cached global feature states.
     */
    public static function flushGlobalFeatureStates(): void
    {
        app(FeatureAccessService::class)->reset();
    }

    /**
     * Get all global feature states in a single batch query.
     *
     * @return array<string, mixed>
     */
    public static function getGlobalFeatureStates(): array
    {
        return app(FeatureAccessService::class)->legacyGlobalStates();
    }

    /**
     * Get a single global feature state from the batch cache.
     */
    public static function getGlobalFeatureState(string $featureClass): mixed
    {
        return self::getGlobalFeatureStates()[$featureClass] ?? null;
    }

    /**
     * Get all registered class names.
     *
     * @return array<int, class-string<FeatureToggle>>
     */
    public static function allClasses(): array
    {
        return array_values(self::KEY_TO_CLASS);
    }

    /**
     * Get the full key-to-class mapping.
     *
     * @return array<string, class-string<FeatureToggle>>
     */
    public static function mapping(): array
    {
        return self::KEY_TO_CLASS;
    }

    /**
     * Instantiate a feature toggle by key.
     */
    public static function make(string $key): ?FeatureToggle
    {
        $class = self::classForKey($key);

        if ($class === null) {
            return null;
        }

        return new $class();
    }

    /**
     * Get all instantiated feature toggles.
     *
     * @return array<int, FeatureToggle>
     */
    public static function all(): array
    {
        return array_map(
            fn (string $class): FeatureToggle => new $class(),
            self::allClasses()
        );
    }
}
