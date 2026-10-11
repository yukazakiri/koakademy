<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Email\EmailTemplateCatalog;
use App\Email\EmailTemplateRenderer;
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
     * @param  array<string, mixed>  $customTemplate
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
        // The policy compiler rejects absolute URLs, so a configured button_url is
        // always a same-portal path. Resolve it against the application URL.
        $portalUrl = $this->resolvePortalUrl($this->customTemplate['button_url'] ?? null);

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

        $renderer = app(EmailTemplateRenderer::class);
        $templateKey = ($this->customTemplate['notification'] ?? null) === 'custom' ? 'custom' : 'status_update';
        $subject = $renderer->subject($templateKey, $tokens, $this->customTemplate);

        // Determine headline
        $rawHeadline = $this->customTemplate['headline'] ?? null;
        $headline = is_string($rawHeadline) && mb_trim($rawHeadline) !== ''
            ? $renderer->interpolate($rawHeadline, $tokens)
            : 'Application Status Update';

        $customBody = $renderer->intro($templateKey, $tokens, $this->customTemplate);
        $rawIntro = $this->customTemplate['intro'] ?? $this->customTemplate['custom_body'] ?? null;
        // Catalog fallback copy is not an administrator-authored replacement for
        // the existing status-specific guidance below the application details.
        $showStatusGuidance = ! is_string($rawIntro) || mb_trim(EmailTemplateCatalog::stripSalutation($rawIntro)) === '';

        $buttonLabel = is_string($this->customTemplate['button_label'] ?? null) && mb_trim($this->customTemplate['button_label']) !== ''
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
                'show_status_guidance' => $showStatusGuidance,
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

    /**
     * The policy compiler rejects absolute URLs inside stored policy configuration,
     * so an administrator-supplied button_url is always a same-portal path. Resolve
     * it against the application URL so the rendered call-to-action stays clickable.
     *
     * Absolute URLs are passed through untouched for callers that construct the
     * template directly (for example the enrollment action handler's own defaults).
     */
    private function resolvePortalUrl(mixed $buttonUrl): string
    {
        $base = mb_rtrim((string) config('app.url'), '/');

        if (! is_string($buttonUrl)) {
            return $base;
        }

        $path = mb_trim($buttonUrl);

        if ($path === '') {
            return $base;
        }

        if (filter_var($path, FILTER_VALIDATE_URL) !== false) {
            return $path;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $base;
        }

        return $base.$path;
    }
}
