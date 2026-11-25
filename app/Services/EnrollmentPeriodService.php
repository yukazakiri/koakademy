<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EnrollmentPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final readonly class EnrollmentPeriodService
{
    public function __construct(
        private GeneralSettingsService $settingsService,
        private TenantContext $tenantContext,
    ) {}

    public function currentPeriod(?string $schoolYear = null, ?int $semester = null): ?EnrollmentPeriod
    {
        if (! Schema::hasTable('enrollment_periods')) {
            return null;
        }

        $resolvedYear = GeneralSettingsService::normalizeSchoolYear(
            $schoolYear ?? $this->settingsService->getCurrentSchoolYearString()
        );
        $resolvedSemester = $semester ?? $this->settingsService->getCurrentSemester();
        $schoolId = $this->tenantContext->getCurrentSchoolId();

        $query = EnrollmentPeriod::query()
            ->where('school_year', $resolvedYear)
            ->where('semester', $resolvedSemester);

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        } else {
            $query->whereNull('school_id');
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertForTerm(
        array $data,
        User $actor,
        ?string $schoolYear = null,
        ?int $semester = null,
    ): EnrollmentPeriod {
        $resolvedYear = GeneralSettingsService::normalizeSchoolYear(
            $schoolYear ?? $this->settingsService->getCurrentSchoolYearString()
        );
        $resolvedSemester = $semester ?? $this->settingsService->getCurrentSemester();
        $schoolId = $this->tenantContext->getCurrentSchoolId();

        $period = $this->currentPeriod($resolvedYear, $resolvedSemester) ?? new EnrollmentPeriod([
            'school_id' => $schoolId,
            'school_year' => $resolvedYear,
            'semester' => $resolvedSemester,
        ]);

        $period->fill([
            'regular_starts_at' => $data['regular_starts_at'] ?? null,
            'regular_ends_at' => $data['regular_ends_at'] ?? null,
            'late_starts_at' => $data['late_starts_at'] ?? null,
            'late_ends_at' => $data['late_ends_at'] ?? null,
            'add_drop_deadline_at' => $data['add_drop_deadline_at'] ?? null,
            'late_fee_amount' => round((float) ($data['late_fee_amount'] ?? 0), 2),
            'late_fee_label' => filled($data['late_fee_label'] ?? null)
                ? mb_trim((string) $data['late_fee_label'])
                : 'Late Enrollment Fee',
            'auto_apply_late_fee' => (bool) ($data['auto_apply_late_fee'] ?? false),
            'enforce_windows' => (bool) ($data['enforce_windows'] ?? false),
            'is_manually_closed' => (bool) ($data['is_manually_closed'] ?? false),
            'notes' => isset($data['notes']) && mb_trim((string) $data['notes']) !== ''
                ? mb_trim((string) $data['notes'])
                : null,
            'updated_by' => $actor->id,
        ]);
        $period->save();

        return $period->fresh();
    }

    /**
     * Compute the live window status for a term period.
     *
     * @return array<string, mixed>
     */
    public function describe(?string $schoolYear = null, ?int $semester = null, ?Carbon $now = null): array
    {
        $resolvedYear = GeneralSettingsService::normalizeSchoolYear(
            $schoolYear ?? $this->settingsService->getCurrentSchoolYearString()
        );
        $resolvedSemester = $semester ?? $this->settingsService->getCurrentSemester();
        $period = $this->currentPeriod($resolvedYear, $resolvedSemester);
        $clock = $now ?? now();

        if (! $period instanceof EnrollmentPeriod) {
            return [
                'id' => null,
                'configured' => false,
                'school_year' => $resolvedYear,
                'semester' => $resolvedSemester,
                'phase' => 'unconfigured',
                'phase_label' => 'Window not set',
                'tone' => 'neutral',
                'can_enroll' => true,
                'is_late' => false,
                'add_drop_open' => true,
                'enforce_windows' => false,
                'is_manually_closed' => false,
                'auto_apply_late_fee' => false,
                'late_fee_amount' => 0.0,
                'late_fee_label' => 'Late Enrollment Fee',
                'regular_starts_at' => null,
                'regular_ends_at' => null,
                'late_starts_at' => null,
                'late_ends_at' => null,
                'add_drop_deadline_at' => null,
                'notes' => null,
                'summary_text' => 'Open continuously (no schedule configured)',
            ];
        }

        $regularStart = $period->regular_starts_at;
        $regularEnd = $period->regular_ends_at;
        $lateStart = $period->late_starts_at ?? $regularEnd;
        $lateEnd = $period->late_ends_at;
        $addDropDeadline = $period->add_drop_deadline_at;

        $hasAnyDate = $regularStart !== null || $regularEnd !== null || $lateStart !== null || $lateEnd !== null;

        $phase = 'regular';
        $phaseLabel = 'Regular Registration';
        $tone = 'success';
        $canEnroll = true;
        $isLate = false;
        $summaryText = 'Regular enrollment open';

        if ($period->is_manually_closed) {
            $phase = 'closed';
            $phaseLabel = 'Registration Closed';
            $tone = 'destructive';
            $canEnroll = false;
            $summaryText = 'Manually closed by administrator';
        } elseif (! $hasAnyDate) {
            $phase = 'regular';
            $phaseLabel = 'Open Registration';
            $tone = 'success';
            $canEnroll = true;
            $summaryText = 'Open registration';
        } elseif ($regularStart !== null && $clock->lt($regularStart)) {
            $phase = 'upcoming';
            $phaseLabel = 'Scheduled';
            $tone = 'info';
            $canEnroll = false;
            $summaryText = 'Opens '.$regularStart->format('M j, Y');
        } elseif ($regularEnd === null || $clock->lte($regularEnd)) {
            $phase = 'regular';
            $phaseLabel = 'Regular Registration';
            $tone = 'success';
            $canEnroll = true;
            $summaryText = $regularEnd !== null
                ? 'Regular closes '.$regularEnd->format('M j, Y')
                : 'Regular registration open';
        } elseif ($lateEnd !== null && $clock->lte($lateEnd) && ($lateStart === null || $clock->gte($lateStart))) {
            $phase = 'late';
            $phaseLabel = 'Late Registration';
            $tone = 'warning';
            $canEnroll = true;
            $isLate = true;
            $feeSuffix = $period->auto_apply_late_fee && (float) $period->late_fee_amount > 0
                ? ' · +'.number_format((float) $period->late_fee_amount, 2).' fee'
                : '';
            $summaryText = 'Late closes '.$lateEnd->format('M j, Y').$feeSuffix;
        } else {
            $phase = 'closed';
            $phaseLabel = 'Registration Closed';
            $tone = 'destructive';
            $canEnroll = false;
            $closedAt = $lateEnd ?? $regularEnd;
            $summaryText = $closedAt !== null
                ? 'Closed '.$closedAt->format('M j, Y')
                : 'Registration closed';
        }

        $addDropOpen = ! $period->is_manually_closed && (
            $addDropDeadline === null || $clock->lte($addDropDeadline)
        );

        return [
            'id' => $period->id,
            'configured' => true,
            'school_year' => $period->school_year,
            'semester' => $period->semester,
            'phase' => $phase,
            'phase_label' => $phaseLabel,
            'tone' => $tone,
            'can_enroll' => $canEnroll,
            'is_late' => $isLate,
            'add_drop_open' => $addDropOpen,
            'enforce_windows' => (bool) $period->enforce_windows,
            'is_manually_closed' => (bool) $period->is_manually_closed,
            'auto_apply_late_fee' => (bool) $period->auto_apply_late_fee,
            'late_fee_amount' => round((float) $period->late_fee_amount, 2),
            'late_fee_label' => $period->late_fee_label ?: 'Late Enrollment Fee',
            'regular_starts_at' => $regularStart?->toIso8601String(),
            'regular_ends_at' => $regularEnd?->toIso8601String(),
            'late_starts_at' => $period->late_starts_at?->toIso8601String(),
            'late_ends_at' => $lateEnd?->toIso8601String(),
            'add_drop_deadline_at' => $addDropDeadline?->toIso8601String(),
            'notes' => $period->notes,
            'summary_text' => $summaryText,
        ];
    }

    /**
     * Assert that enrollment is permitted for the term (unless explicitly overridden).
     *
     * @return array<string, mixed> The described period status.
     */
    public function assertEnrollmentAllowed(
        bool $overrideWindow = false,
        ?string $schoolYear = null,
        ?int $semester = null,
        ?Carbon $now = null,
    ): array {
        $status = $this->describe($schoolYear, $semester, $now);

        if ($status['enforce_windows'] && ! $status['can_enroll'] && ! $overrideWindow) {
            throw new RuntimeException(
                sprintf(
                    'Enrollment registration is currently %s (%s). Enable administrator window override to proceed.',
                    mb_strtolower((string) $status['phase_label']),
                    $status['summary_text'],
                ),
            );
        }

        return $status;
    }

    /**
     * Append the configured automatic late fee if currently inside the late registration window.
     *
     * @param  array<int, array<string, mixed>>  $additionalFees
     * @param  array<string, mixed>  $periodStatus
     * @return array<int, array<string, mixed>>
     */
    public function appendLateFeeIfApplicable(array $additionalFees, array $periodStatus): array
    {
        if (
            ! ($periodStatus['is_late'] ?? false)
            || ! ($periodStatus['auto_apply_late_fee'] ?? false)
            || (float) ($periodStatus['late_fee_amount'] ?? 0) <= 0
        ) {
            return $additionalFees;
        }

        $feeLabel = (string) ($periodStatus['late_fee_label'] ?? 'Late Enrollment Fee');
        $alreadyPresent = collect($additionalFees)->contains(
            fn (array $fee): bool => mb_strtolower(mb_trim((string) ($fee['fee_name'] ?? ''))) === mb_strtolower($feeLabel),
        );

        if ($alreadyPresent) {
            return $additionalFees;
        }

        return [
            ...$additionalFees,
            [
                'fee_name' => $feeLabel,
                'amount' => round((float) $periodStatus['late_fee_amount'], 2),
            ],
        ];
    }

    public function isAddDropOpen(?string $schoolYear = null, ?int $semester = null, ?Carbon $now = null): bool
    {
        return (bool) $this->describe($schoolYear, $semester, $now)['add_drop_open'];
    }
}
