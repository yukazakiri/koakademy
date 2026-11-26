<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\StudentType;
use App\Models\ClassAttendanceRecord;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Services\GeneralSettingsService;
use App\Services\GradeEvaluationService;
use App\Services\GradingSystemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class FacultyAtRiskController extends Controller
{
    public function index(
        Request $request,
        GeneralSettingsService $settingsService,
        GradingSystemService $gradingSystem,
        GradeEvaluationService $gradeEvaluation,
    ): Response {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $faculty = Faculty::query()->where('email', $user->email)->first();

        if (! $faculty) {
            abort(403);
        }

        $currentSemester = (string) $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();
        $policy = $gradingSystem->getConfig();

        $classes = $faculty->classes()
            ->currentAcademicPeriod()
            ->with(['subject', 'SubjectByCodeFallback', 'ShsSubject', 'Room'])
            ->orderBy('subject_code')
            ->orderBy('section')
            ->get();

        $classIds = $classes->pluck('id')->all();
        $classesById = $classes->keyBy('id');

        $classOptions = $classes->map(function (Classes $class): array {
            $primarySubject = $this->resolvePrimarySubject($class);
            $code = $primarySubject?->code ?? $class->subject_code ?? 'N/A';
            $title = $primarySubject?->title ?? 'Untitled Subject';
            $section = $class->section ?? 'N/A';

            return [
                'id' => (int) $class->id,
                'subject_code' => $code,
                'subject_title' => $title,
                'section' => $section,
                'label' => mb_trim("{$code} · {$section}"),
            ];
        })->values()->all();

        $allAlerts = collect();

        if (! empty($classIds)) {
            $enrollments = ClassEnrollment::query()
                ->whereIn('class_id', $classIds)
                ->where('status', true)
                ->with(['student'])
                ->get();

            $attendanceRows = ClassAttendanceRecord::query()
                ->whereIn('class_id', $classIds)
                ->whereIn('status', [AttendanceStatus::Absent->value, AttendanceStatus::Late->value])
                ->selectRaw('class_id, student_id, class_enrollment_id, status, COUNT(*) as total')
                ->groupBy('class_id', 'student_id', 'class_enrollment_id', 'status')
                ->get();

            $attendanceByEnrollment = [];
            $attendanceByClassStudent = [];

            foreach ($attendanceRows as $row) {
                $statusKey = $row->status instanceof AttendanceStatus ? $row->status->value : (string) $row->status;
                $count = (int) $row->total;

                if ($row->class_enrollment_id !== null) {
                    $enrollmentKey = (int) $row->class_enrollment_id;
                    $attendanceByEnrollment[$enrollmentKey][$statusKey] = ($attendanceByEnrollment[$enrollmentKey][$statusKey] ?? 0) + $count;
                }

                if ($row->student_id !== null) {
                    $pairKey = "{$row->class_id}:{$row->student_id}";
                    $attendanceByClassStudent[$pairKey][$statusKey] = ($attendanceByClassStudent[$pairKey][$statusKey] ?? 0) + $count;
                }
            }

            foreach ($enrollments as $enrollment) {
                /** @var Classes|null $class */
                $class = $classesById->get($enrollment->class_id);
                if (! $class instanceof Classes) {
                    continue;
                }

                $student = $enrollment->student;
                $enrollmentKey = (int) $enrollment->id;
                $pairKey = "{$enrollment->class_id}:{$enrollment->student_id}";

                $counts = $attendanceByEnrollment[$enrollmentKey]
                    ?? $attendanceByClassStudent[$pairKey]
                    ?? [];

                $absences = (int) ($counts[AttendanceStatus::Absent->value] ?? 0);
                $lates = (int) ($counts[AttendanceStatus::Late->value] ?? 0);

                $signals = [];
                $categories = [];

                $hasAttendanceRisk = false;
                if ($absences >= 3) {
                    $hasAttendanceRisk = true;
                    $categories[] = 'attendance';
                    $signals[] = sprintf('%d absence%s recorded', $absences, $absences === 1 ? '' : 's');
                }
                if ($lates >= 4) {
                    $hasAttendanceRisk = true;
                    if (! in_array('attendance', $categories, true)) {
                        $categories[] = 'attendance';
                    }
                    $signals[] = sprintf('%d late arrivals', $lates);
                } elseif ($hasAttendanceRisk && $lates > 0) {
                    $signals[] = sprintf('%d late arrival%s', $lates, $lates === 1 ? '' : 's');
                }

                $failingGradeSignals = $this->detectFailingGrades($enrollment, $policy, $gradeEvaluation);
                $hasAcademicRisk = ! empty($failingGradeSignals);

                if ($hasAcademicRisk) {
                    $categories[] = 'academic';
                    array_push($signals, ...$failingGradeSignals);
                }

                if (! $hasAttendanceRisk && ! $hasAcademicRisk) {
                    continue;
                }

                $hasFailingAverage = $enrollment->total_average !== null
                    && $this->isFailingNumeric((float) $enrollment->total_average, $policy, $gradeEvaluation);

                $isHighRisk = ($hasAttendanceRisk && $hasAcademicRisk)
                    || $absences >= 5
                    || $hasFailingAverage
                    || count($failingGradeSignals) >= 2;

                $riskLevel = $isHighRisk ? 'high' : 'medium';

                $primarySubject = $this->resolvePrimarySubject($class);
                $subjectCode = $primarySubject?->code ?? $class->subject_code ?? 'N/A';
                $subjectTitle = $primarySubject?->title ?? 'Untitled Subject';
                $section = $class->section ?? 'N/A';

                $studentName = $student?->full_name
                    ?? mb_trim(implode(' ', array_filter([$student?->first_name, $student?->middle_name, $student?->last_name])))
                    ?: 'Student #'.$enrollment->student_id;

                $studentIdentifier = $student?->student_type === StudentType::SeniorHighSchool
                    ? ($student->lrn ?? 'N/A')
                    : ($student?->student_id !== null ? (string) $student->student_id : 'N/A');

                $allAlerts->push([
                    'id' => (int) $enrollment->id,
                    'class_id' => (int) $class->id,
                    'subject_code' => $subjectCode,
                    'subject_title' => $subjectTitle,
                    'section' => $section,
                    'class_label' => mb_trim("{$subjectCode} · {$section}"),
                    'student_db_id' => $student?->id,
                    'student_id' => $studentIdentifier,
                    'student_name' => $studentName,
                    'student_email' => $student?->email,
                    'risk_level' => $riskLevel,
                    'risk_categories' => $categories,
                    'absences_count' => $absences,
                    'lates_count' => $lates,
                    'grades' => [
                        'prelim' => $enrollment->prelim_grade !== null ? (float) $enrollment->prelim_grade : null,
                        'midterm' => $enrollment->midterm_grade !== null ? (float) $enrollment->midterm_grade : null,
                        'final' => $enrollment->finals_grade !== null ? (float) $enrollment->finals_grade : null,
                        'average' => $enrollment->total_average !== null ? (float) $enrollment->total_average : null,
                        'outcome' => $enrollment->grade_outcome,
                    ],
                    'signals' => $signals,
                    'signal_count' => count($signals),
                    'links' => [
                        'open_class' => "/faculty/classes/{$class->id}",
                        'view_grades' => "/faculty/classes/{$class->id}?tab=grades",
                        'take_attendance' => "/faculty/classes/{$class->id}?tab=attendance",
                    ],
                ]);
            }
        }

        $sortedAlerts = $allAlerts
            ->sort(function (array $left, array $right): int {
                $leftRank = $left['risk_level'] === 'high' ? 0 : 1;
                $rightRank = $right['risk_level'] === 'high' ? 0 : 1;
                if ($leftRank !== $rightRank) {
                    return $leftRank <=> $rightRank;
                }

                if ($left['signal_count'] !== $right['signal_count']) {
                    return $right['signal_count'] <=> $left['signal_count'];
                }

                if ($left['absences_count'] !== $right['absences_count']) {
                    return $right['absences_count'] <=> $left['absences_count'];
                }

                return strcmp((string) $left['student_name'], (string) $right['student_name']);
            })
            ->values();

        $summary = [
            'high_risk' => $sortedAlerts->where('risk_level', 'high')->count(),
            'medium_risk' => $sortedAlerts->where('risk_level', 'medium')->count(),
            'attendance_risk' => $sortedAlerts->filter(fn (array $alert): bool => in_array('attendance', $alert['risk_categories'], true))->count(),
            'academic_risk' => $sortedAlerts->filter(fn (array $alert): bool => in_array('academic', $alert['risk_categories'], true))->count(),
            'classes_monitored' => $classes->count(),
            'total_alerts' => $sortedAlerts->count(),
        ];

        $selectedClassId = $request->query('class_id');
        $selectedClassIdInt = is_numeric($selectedClassId) ? (int) $selectedClassId : null;
        if ($selectedClassIdInt !== null && ! in_array($selectedClassIdInt, $classIds, true)) {
            $selectedClassIdInt = null;
        }

        $selectedRiskLevel = (string) $request->query('risk_level', 'all');
        if (! in_array($selectedRiskLevel, ['all', 'high', 'medium'], true)) {
            $selectedRiskLevel = 'all';
        }

        $filteredAlerts = $sortedAlerts
            ->when($selectedClassIdInt !== null, fn ($collection) => $collection->where('class_id', $selectedClassIdInt))
            ->when($selectedRiskLevel !== 'all', fn ($collection) => $collection->where('risk_level', $selectedRiskLevel))
            ->values()
            ->all();

        return Inertia::render('faculty/at-risk-alerts/index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $faculty->getFilamentAvatarUrl() ?: ($user->avatar_url ?? null),
                'role' => $user->role?->getLabel() ?? 'Faculty',
            ],
            'alerts' => $filteredAlerts,
            'classes' => $classOptions,
            'summary' => $summary,
            'filters' => [
                'class_id' => $selectedClassIdInt !== null ? (string) $selectedClassIdInt : 'all',
                'risk_level' => $selectedRiskLevel,
            ],
            'current_semester' => $currentSemester,
            'current_school_year' => $currentSchoolYear,
            'flash' => session('flash'),
        ]);
    }

    private function resolvePrimarySubject(Classes $class): mixed
    {
        $primarySubject = $class->classification === 'shs'
            ? $class->ShsSubject
            : ($class->subject ?: $class->SubjectByCodeFallback);

        if (! $primarySubject) {
            $primarySubject = $class->subjects->first();
        }

        return $primarySubject;
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return list<string>
     */
    private function detectFailingGrades(
        ClassEnrollment $enrollment,
        array $policy,
        GradeEvaluationService $gradeEvaluation,
    ): array {
        $reasons = [];

        $gradeChecks = [
            'Prelim' => $enrollment->prelim_grade,
            'Midterm' => $enrollment->midterm_grade,
            'Final' => $enrollment->finals_grade,
            'Average' => $enrollment->total_average,
        ];

        foreach ($gradeChecks as $label => $value) {
            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }

            $numeric = (float) $value;
            if ($this->isFailingNumeric($numeric, $policy, $gradeEvaluation)) {
                $reasons[] = sprintf('Failing %s (%.2f)', $label, $numeric);
            }
        }

        if (empty($reasons) && $enrollment->grade_outcome === 'fail') {
            $symbolSuffix = $enrollment->grade_symbol ? " ({$enrollment->grade_symbol})" : '';
            $reasons[] = "Failing grade outcome{$symbolSuffix}";
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    private function isFailingNumeric(float $score, array $policy, GradeEvaluationService $gradeEvaluation): bool
    {
        if (($policy['input_type'] ?? 'numeric') === 'numeric') {
            $evaluation = $gradeEvaluation->evaluate($score, $policy);
            if ($evaluation['outcome'] === 'fail') {
                return true;
            }
            if ($evaluation['outcome'] === 'pass') {
                return false;
            }
        }

        $direction = $policy['direction'] ?? 'higher_is_better';
        $max = (float) ($policy['numeric_max'] ?? 100);

        if ($direction === 'lower_is_better' || $max <= 5.0) {
            return $score > 3.0;
        }

        return $score < 75.0;
    }
}
