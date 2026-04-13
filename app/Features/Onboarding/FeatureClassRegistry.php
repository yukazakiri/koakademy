<?php

declare(strict_types=1);

namespace App\Features\Onboarding;

/**
 * Registry mapping onboarding feature keys to their Pennant class names.
 * Used by controllers and services to resolve string keys to class references.
 */
final class FeatureClassRegistry
{
    /**
     * Map of feature_key => Pennant feature class name.
     *
     * @var array<string, class-string>
     */
    private const array KEY_TO_CLASS = [
        // Faculty features
        'onboarding-faculty-toolkit' => FacultyToolkit::class,
        'onboarding-faculty-at-risk-alerts' => FacultyAtRiskAlerts::class,
        'onboarding-faculty-assessments' => FacultyAssessments::class,
        'onboarding-faculty-inbox' => FacultyInbox::class,
        'onboarding-faculty-office-hours' => FacultyOfficeHours::class,
        'onboarding-faculty-requests-approvals' => FacultyRequestsApprovals::class,
        'onboarding-faculty-insights' => FacultyInsights::class,
        'onboarding-faculty-grades' => FacultyGrades::class,
        'onboarding-faculty-attendance' => FacultyAttendance::class,
        'onboarding-faculty-resources' => FacultyResources::class,
        'onboarding-faculty-forms' => FacultyForms::class,
        'onboarding-faculty-developer-mode' => FacultyDeveloperMode::class,
        'onboarding-faculty-dashboard' => FacultyDashboard::class,
        'onboarding-faculty-action-center' => FacultyActionCenter::class,
        'onboarding-faculty-classes' => FacultyClasses::class,
        'onboarding-faculty-schedule' => FacultySchedule::class,
        'onboarding-faculty-announcements' => FacultyAnnouncements::class,
        'onboarding-faculty-settings' => FacultySettings::class,
        'onboarding-faculty-help' => FacultyHelp::class,

        // Student features
        'onboarding-student-dashboard' => StudentDashboard::class,
        'onboarding-student-classes' => StudentClasses::class,
        'onboarding-student-tuition' => StudentTuition::class,
        'onboarding-student-schedule' => StudentSchedule::class,
        'onboarding-student-announcements' => StudentAnnouncements::class,
        'onboarding-student-settings' => StudentSettings::class,
        'onboarding-student-help' => StudentHelp::class,
        'onboarding-student-grades-preview' => StudentGradesPreview::class,
        'onboarding-student-attendance-tracker' => StudentAttendanceTracker::class,
        'onboarding-student-developer-mode' => StudentDeveloperMode::class,

        // Generic features
        'default-onboarding' => DefaultOnboarding::class,
        'onboarding-faculty' => OnboardingFaculty::class,
        'onboarding-student' => OnboardingStudent::class,

        // Student detail features (non-onboarding, but class-based)
        'student-signature-pad' => \App\Features\StudentSignaturePad::class,
        'student-avatar-upload' => \App\Features\StudentAvatarUpload::class,

        // Enrollment features
        'online-college-enrollment' => \App\Features\OnlineCollegeEnrollment::class,
        'online-tesda-enrollment' => \App\Features\OnlineTesdaEnrollment::class,
    ];

    /**
     * Get the class name for a feature key.
     *
     * @return class-string|null
     */
    public static function classForKey(string $key): ?string
    {
        return self::KEY_TO_CLASS[$key] ?? null;
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
     * Get all registered class names.
     *
     * @return array<int, class-string>
     */
    public static function allClasses(): array
    {
        return array_values(self::KEY_TO_CLASS);
    }

    /**
     * Get the full key-to-class mapping.
     *
     * @return array<string, class-string>
     */
    public static function mapping(): array
    {
        return self::KEY_TO_CLASS;
    }
}
