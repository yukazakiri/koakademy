<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GeneralSetting;
use App\Models\StudentEnrollment;
use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EnrollmentStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $customTemplate
     */
    public function __construct(
        public StudentEnrollment $record,
        public ?string $stepKey = null,
        public array $customTemplate = []
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteSettings = app(SiteSettings::class)->getBrandingArray();
        $generalSettings = GeneralSetting::query()->first();

        $logoUrl = $siteSettings['logo'] ?? null;
        if ($logoUrl && ! str_starts_with((string) $logoUrl, 'http')) {
            $logoUrl = url($logoUrl);
        }

        $status = (string) ($this->record->status ?? 'Updated');
        $organizationName = (string) ($siteSettings['organizationName'] ?? config('app.name'));
        $stepName = (string) ($this->stepKey ?? $this->record->current_step_key ?? 'Enrollment Pipeline');
        $formattedStep = ucwords(str_replace(['_', '-'], ' ', $stepName));

        $studentName = (string) ($this->record->student_name
            ?? $this->record->first_name
            ?? $this->record->student?->full_name
            ?? 'Student');

        $schoolYear = (string) ($this->record->school_year ?? $generalSettings?->getSchoolYearString() ?? '');
        $semester = (string) ($this->record->semester ?? $generalSettings?->getSemester() ?? '');
        $program = (string) ($this->record->Course?->code ?? $this->record->Course?->title ?? '');
        $portalUrl = (string) ($this->customTemplate['button_url'] ?? config('app.url'));

        $tokens = [
            'student_name' => $studentName,
            'status' => $status,
            'step_name' => $formattedStep,
            'school_year' => $schoolYear,
            'semester' => $semester,
            'program' => $program,
            'portal_url' => $portalUrl,
            'organization_name' => $organizationName,
        ];

        $interpolate = function (?string $text) use ($tokens): ?string {
            if ($text === null || $text === '') {
                return null;
            }
            $result = $text;
            foreach ($tokens as $key => $val) {
                $result = str_replace(
                    ['{{ ' . $key . ' }}', '{{' . $key . '}}'],
                    $val,
                    $result
                );
            }
            return $result;
        };

        // Determine subject
        $rawSubject = $this->customTemplate['subject'] ?? null;
        $subject = is_string($rawSubject) && trim($rawSubject) !== ''
            ? ($interpolate($rawSubject) ?? "Enrollment Status Update: {$status} | {$organizationName}")
            : sprintf('Enrollment Status Update: %s | %s', $status, $organizationName);

        // Determine headline
        $rawHeadline = $this->customTemplate['headline'] ?? null;
        $headline = is_string($rawHeadline) && trim($rawHeadline) !== ''
            ? ($interpolate($rawHeadline) ?? 'Application Status Update')
            : 'Application Status Update';

        // Determine custom body
        $rawBody = $this->customTemplate['custom_body'] ?? null;
        $customBody = is_string($rawBody) && trim($rawBody) !== ''
            ? $interpolate($rawBody)
            : null;

        $buttonLabel = is_string($this->customTemplate['button_label'] ?? null) && trim($this->customTemplate['button_label']) !== ''
            ? (string) $this->customTemplate['button_label']
            : 'Visit Student Portal';

        $includeDetails = (bool) ($this->customTemplate['include_details'] ?? true);
        $includeSubjects = (bool) ($this->customTemplate['include_subjects'] ?? false);
        $includeTuition = (bool) ($this->customTemplate['include_tuition'] ?? false);

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.enrollment.status_update', [
                'student_name' => $studentName,
                'status' => $status,
                'headline' => $headline,
                'custom_body' => $customBody,
                'button_label' => $buttonLabel,
                'portalUrl' => $portalUrl,
                'step_name' => $formattedStep,
                'school_year' => $schoolYear,
                'semester' => $semester,
                'program' => $program,
                'include_details' => $includeDetails,
                'include_subjects' => $includeSubjects,
                'include_tuition' => $includeTuition,
                'subjects' => $this->record->SubjectsEnrolled,
                'tuition' => $this->record->studentTuition,
                'siteSettings' => $siteSettings,
                'logoUrl' => $logoUrl,
            ]);
    }
}
