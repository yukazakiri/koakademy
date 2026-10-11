<?php

declare(strict_types=1);

namespace App\Services;

use App\Email\EmailTemplateCatalog;
use App\Email\EnrollmentAssessmentMailMessage;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Notifications\EnrollmentStatusUpdatedNotification;
use App\Notifications\StudentEnrolledVerified;
use App\Settings\SiteSettings;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use LogicException;
use stdClass;

final readonly class EnrollmentEmailTemplatePreviewService
{
    public function __construct(
        private SiteSettings $settings,
        private EnrollmentAssessmentMailMessage $assessmentMessage,
    ) {}

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    public function editorProps(array $step): array
    {
        $organizationName = $this->settings->getOrganizationName();
        $definitions = [];
        $labels = array_column(EnrollmentEmailTemplateService::NOTIFICATION_TYPES, 'label', 'value');

        foreach (EmailTemplateCatalog::ENROLLMENT_KEYS as $key) {
            $default = EmailTemplateCatalog::defaultFor($key)
                ?? throw new LogicException("Missing enrollment email defaults for {$key}.");
            $status = in_array($key, ['status_update', 'custom'], true);
            $academic = $key === 'academic_verified';
            $definitions[] = [
                'key' => $key,
                'label' => $labels[$key],
                'description' => match ($key) {
                    'academic_verified' => 'Academic clearance with code-owned approved subjects and fees.',
                    'assessment' => 'Enrollment confirmation with a code-owned assessment PDF and portal instructions.',
                    default => 'Workflow progress with optional application details, subjects and fees.',
                },
                'defaults' => [
                    'notification' => $key,
                    'subject' => $default['subject'],
                    'custom_body' => $default['intro'],
                    'headline' => match ($key) {
                        'academic_verified' => 'Enrollment Verified',
                        'assessment' => 'Welcome to '.$organizationName.'!',
                        default => 'Application Status Update',
                    },
                    'button_label' => $key === 'assessment' ? 'Access Student Portal' : 'Visit Student Portal',
                    // Empty means the application root in the status sender.
                    'button_url' => '',
                    'include_details' => $status || $key === 'assessment',
                    'include_subjects' => $academic,
                    'include_tuition' => $academic,
                ],
                'capabilities' => [
                    'headline' => $status,
                    'button_label' => $status || $academic,
                    'button_url' => $status,
                    'sections' => $status,
                ],
            ];
        }

        return [
            'definitions' => $definitions,
            'sample_tokens' => $this->sampleTokens($step),
            'branding' => [
                'organization_name' => $organizationName,
                'logo_url' => URL::to($this->settings->getLogo()),
                'tagline' => $this->settings->getTagline(),
                'theme_color' => $this->settings->getThemeColor(),
                'support_email' => $this->settings->getSupportEmail(),
                'support_phone' => $this->settings->getSupportPhone(),
            ],
        ];
    }

    /**
     * No persisted sample records, account lookups, attachment reads or delivery.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $configuration
     * @return array{subject: string, html: string}
     */
    public function preview(array $step, array $configuration): array
    {
        $tokens = $this->sampleTokens($step);
        $branding = $this->settings->getBrandingArray();

        if ($configuration['notification'] === 'assessment') {
            $message = $this->assessmentMessage->build(
                $tokens,
                $configuration,
                $branding,
                URL::to($this->settings->getLogo()),
                ['has_account' => true, 'student_id_display' => null, 'student_email' => null, 'signup_url' => null],
                pdfAttached: true,
            );
        } else {
            $record = $this->sampleEnrollment($tokens);
            $notification = $configuration['notification'] === 'academic_verified'
                ? new StudentEnrolledVerified($record, $configuration)
                : new EnrollmentStatusUpdatedNotification($record, $tokens['step_name'], $configuration);
            $message = $notification->toMail(new stdClass);
        }

        return [
            'subject' => $message->subject,
            'html' => (string) app(Markdown::class)->render(
                $message->markdown ?? throw new LogicException('Enrollment email previews require a Markdown view.'),
                $message->data(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, string>
     */
    private function sampleTokens(array $step): array
    {
        $default = EmailTemplateCatalog::defaultFor('status_update')
            ?? throw new LogicException('Missing enrollment status email defaults.');
        $status = $step['step_status'] ?? 'Updated';
        $stepKey = $step['step_key'] ?? '';

        if (! is_string($status) || ! is_string($stepKey)) {
            throw new LogicException('Enrollment email previews require a named workflow step and status.');
        }

        return [
            ...$default['tokens'],
            'student_name' => 'Santos, Maria',
            'status' => $status,
            'step_name' => ucwords(str_replace(['_', '-'], ' ', $stepKey)),
            'portal_url' => Config::string('app.url'),
            'organization_name' => $this->settings->getOrganizationName(),
        ];
    }

    /** @param  array<string, string>  $tokens */
    private function sampleEnrollment(array $tokens): StudentEnrollment
    {
        $student = new Student;
        $student->setRawAttributes(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $course = new Course;
        $course->setRawAttributes(['code' => $tokens['program'], 'title' => 'Sample Computer Science Program']);
        $tuition = new StudentTuition;
        $tuition->setRawAttributes(['overall_tuition' => 15000, 'downpayment' => 3000]);
        $record = new StudentEnrollment;
        $record->setRawAttributes([
            'status' => $tokens['status'],
            'school_year' => $tokens['school_year'],
            'semester' => $tokens['semester'],
            'current_step_key' => $tokens['step_name'],
        ]);
        $record->setRelation('student', $student);
        $record->setRelation('Course', $course);
        $record->setRelation('studentTuition', $tuition);
        $record->setRelation('SubjectsEnrolled', new Collection([
            (object) ['subject' => (object) ['code' => 'SAMPLE-CS101', 'title' => 'Sample Introduction to Computing', 'units' => 3]],
            (object) ['subject' => (object) ['code' => 'SAMPLE-MATH101', 'title' => 'Sample College Mathematics', 'units' => 3]],
        ]));

        return $record;
    }
}
