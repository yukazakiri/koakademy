<?php

declare(strict_types=1);

namespace App\Services;

use App\Enrollment\EnrollmentFeeCalculator;
use App\Enrollment\EnrollmentFeePolicy;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Settings\SiteSettings;

final readonly class AssessmentFormDataService
{
    private const array DAYS_OF_WEEK = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
    ];

    public function __construct(
        private EnrollmentBillingService $enrollmentBillingService,
        private GeneralSettingsService $settingsService,
        private SiteSettings $siteSettings,
        private EnrollmentFeeCalculator $feeCalculator,
        private EnrollmentFeePolicy $feePolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(StudentEnrollment $enrollment): array
    {
        $enrollment->loadMissing([
            'student.Course',
            'subjectsEnrolled.subject.course',
            'subjectsEnrolled.class.Schedule.room',
            'subjectsEnrolled.class.Room',
            'studentTuition',
            'additionalFees',
        ]);

        $configuration = $this->feePolicy->forEnrollment($enrollment);
        $subjects = $enrollment->subjectsEnrolled->map(function ($subjectEnrollment) use ($configuration): array {
            $subject = $subjectEnrollment->subject;
            $class = $subjectEnrollment->class;
            $isModular = (bool) ($subjectEnrollment->is_modular ?? false);
            $excludeFromTuition = (bool) $subjectEnrollment->exclude_from_tuition;
            $fees = $this->feeCalculator->subject($subject?->toArray() ?? [], $configuration, $isModular, $subjectEnrollment->toArray());
            $lectureFee = $fees['lecture_fee'];
            $laboratoryFee = $fees['laboratory_fee'];

            if ($excludeFromTuition) {
                $lectureFee = 0.0;
                $laboratoryFee = 0.0;
            }

            return [
                'code' => $subject?->code ?? $subjectEnrollment->external_subject_code ?? 'N/A',
                'title' => $subject?->title ?? $subjectEnrollment->external_subject_title ?? 'Unknown Subject',
                'units' => (float) ($subject?->units ?? $subjectEnrollment->external_subject_units ?? 0),
                'is_modular' => $isModular,
                'exclude_from_tuition' => $excludeFromTuition,
                'lecture_fee' => $lectureFee,
                'laboratory_fee' => $laboratoryFee,
                'class_id' => $subjectEnrollment->class_id,
                'schedule' => $this->buildScheduleByDay($class),
            ];
        })->values();

        $totalUnits = $subjects->sum('units');
        $totalLecture = $subjects->sum('lecture_fee');
        $totalLaboratory = $subjects->sum('laboratory_fee');
        $totalModularSubjects = $subjects
            ->where('is_modular', true)
            ->where('exclude_from_tuition', false)
            ->count();
        $totalModularFee = $totalModularSubjects * (float) $configuration['modular_fee'];

        $additionalFees = $enrollment->additionalFees->map(fn ($fee): array => [
            'name' => $fee->fee_name,
            'amount' => (float) $fee->amount,
            'is_required' => (bool) $fee->is_required,
        ])->values();

        $additionalFeesTotal = (float) $enrollment->additionalFees->sum('amount');

        $tuition = $enrollment->studentTuition;
        $totalAmount = $tuition instanceof StudentTuition
            ? (float) ($tuition->overall_tuition ?? 0)
            : $totalLecture + $totalLaboratory + $additionalFeesTotal;
        $calculatedBalance = null;

        if ($tuition instanceof StudentTuition) {
            $tuition = $this->enrollmentBillingService->syncTuitionBalance($tuition);
            $calculatedBalance = $this->enrollmentBillingService->balanceDue($tuition);
        }

        $generalSettings = $this->settingsService->getGlobalSettingsModel();

        return [
            'student' => [
                'full_name' => $enrollment->student->full_name,
                'student_id' => $enrollment->student->student_id,
                'course_code' => $enrollment->student->Course?->code,
            ],
            'enrollment' => [
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'semester_label' => $this->settingsService->getAvailableSemesters()[$enrollment->semester] ?? '',
            ],
            'subjects' => $subjects,
            'totals' => [
                'units' => $totalUnits,
                'lecture' => $totalLecture,
                'laboratory' => $totalLaboratory,
                'modular_subjects' => $totalModularSubjects,
                'modular_fee' => $totalModularFee,
                'modular_fee_per_subject' => (float) $configuration['modular_fee'],
            ],
            'additional_fees' => $additionalFees,
            'additional_fees_total' => $additionalFeesTotal,
            'tuition' => $tuition instanceof StudentTuition ? [
                'total_lectures' => (float) $tuition->total_lectures,
                'total_laboratory' => (float) $tuition->total_laboratory,
                'total_miscelaneous_fees' => (float) $tuition->total_miscelaneous_fees,
                'assessment_adjustment' => (float) ($tuition->assessment_adjustment ?? 0),
                'discount' => (float) $tuition->discount,
                'discount_scope' => $configuration['discount_scope'] ?? 'lecture_only',
                'downpayment' => (float) $tuition->downpayment,
                'overall_tuition' => (float) $tuition->overall_tuition,
                'total_balance' => (float) ($calculatedBalance ?? $tuition->total_balance),
            ] : null,
            'total_amount' => $totalAmount,
            'school' => [
                'name' => $generalSettings?->school_portal_title ?? $generalSettings?->site_name ?? $this->siteSettings->getOrganizationName(),
                'logo' => $this->resolveLogo($generalSettings?->school_portal_logo),
                'contact' => $generalSettings?->support_phone ?? $this->siteSettings->getSupportPhone() ?? '',
                'email' => $generalSettings?->support_email ?? $this->siteSettings->getSupportEmail() ?? '',
                'address' => $this->siteSettings->getOrganizationAddress() ?? '',
            ],
            'generated_at' => now()->format('m-d-Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(StudentEnrollment $enrollment): array
    {
        $assessment = $this->build($enrollment);

        return [
            'assessment' => $assessment,
            'student' => $enrollment,
            'subjects' => $assessment['subjects'],
            'school_year' => $assessment['enrollment']['school_year'],
            'semester' => $assessment['enrollment']['semester_label'],
            'tuition' => $enrollment->studentTuition,
            'general_settings' => $this->settingsService->getGlobalSettingsModel(),
            'siteSettings' => $this->siteSettings->getBrandingArray(),
        ];
    }

    /**
     * @return array<string, array<int, array{time: string, section: string, room: string, label: string}>>
     */
    private function buildScheduleByDay(mixed $class): array
    {
        $scheduleByDay = array_fill_keys(self::DAYS_OF_WEEK, []);

        if ($class === null) {
            return $scheduleByDay;
        }

        foreach ($class->Schedule as $schedule) {
            $day = mb_strtolower((string) $schedule->day_of_week);

            if (! array_key_exists($day, $scheduleByDay)) {
                continue;
            }

            $time = sprintf(
                '%s-%s',
                $schedule->start_time?->format('g:i A') ?? 'TBA',
                $schedule->end_time?->format('g:i A') ?? 'TBA',
            );
            $section = (string) ($class->section ?? '');
            $room = (string) ($schedule->room?->name ?? $class->Room?->name ?? 'TBA');

            $scheduleByDay[$day][] = [
                'time' => $time,
                'section' => $section,
                'room' => $room,
                'label' => mb_trim(sprintf('%s %s (%s)', $time, $section, $room)),
            ];
        }

        return $scheduleByDay;
    }

    private function resolveLogo(?string $settingsLogo): string
    {
        if (! in_array($settingsLogo, [null, '', '0'], true)) {
            if (str_starts_with($settingsLogo, 'http://') || str_starts_with($settingsLogo, 'https://')) {
                return $settingsLogo;
            }

            return asset($settingsLogo);
        }

        $brandingLogo = $this->siteSettings->getLogo();

        if (! in_array($brandingLogo, [null, '', '0'], true)) {
            return $brandingLogo;
        }

        return asset('logo.png');
    }
}
