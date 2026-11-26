<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Features\Toggles\StudentInformationUpdates;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\DigitalIdCardService;
use App\Services\GeneralSettingsService;
use App\Services\GradeReleaseService;
use App\Services\StudentHoldService;
use App\Services\StudentProfileCompletionService;
use DateTimeInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use Modules\Announcement\Services\AnnouncementDataService;

final class StudentDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Prefer direct user_id binding, falling back ONLY to unclaimed student records matching email
        $student = Student::query()
            ->with(['Course', 'shsStrand', 'DocumentLocation'])
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($unclaimed) use ($user): void {
                        $unclaimed->whereNull('user_id')
                            ->whereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim((string) $user->email))]);
                    });
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->first();

        $generalSettingsService = app(GeneralSettingsService::class);
        $settings = $generalSettingsService->getGlobalSettingsModel();

        // Default data structure
        $studentData = [
            'student_id' => $student?->student_id ?? 'N/A',
            'student_name' => $student?->full_name ?? $user->name,
            'course' => $student?->Course?->title ?? $student?->Course?->name ?? $student?->shsStrand?->name ?? null,
            'academic_year' => $student?->academic_year ?? 1,
            'semester' => $settings?->semester ?? 1,
            'school_year' => $generalSettingsService->getCurrentSchoolYearString(),
            'enrolled_classes' => [],
            'announcements' => [],
            'total_units' => 0,
            'tuition_balance' => 0,
            'clearance_status' => null,
            'grade_clearance_required' => app(GradeReleaseService::class)->requiresClearance(),
            'active_holds' => [],
            'enrollment_journey' => null,
            'document_summary' => null,
        ];

        if ($student) {
            $gradeReleases = app(GradeReleaseService::class);
            $publishedGrades = $gradeReleases->releases($student);
            // Get enrolled classes for current semester
            $currentClasses = $student->getCurrentClasses();
            $enrolledClasses = [];
            $totalUnits = 0;

            foreach ($currentClasses as $enrollment) {
                $class = $enrollment->class;
                if (! $class) {
                    continue;
                }

                // Try to get subject info
                $subject = $class->subject ?? $class->subjectByCode ?? $class->subjectByCodeFallback ?? $class->shsSubject;
                $subjectTitle = $subject?->title ?? $class->subject_code ?? 'Unknown';
                $units = $subject?->units ?? 0;
                $totalUnits += $units;

                // Format schedule
                $schedule = $this->formatSchedule($class);
                $published = $gradeReleases->publishedClass($enrollment, $publishedGrades, $student);

                $enrolledClasses[] = [
                    'id' => $class->id,
                    'subject_code' => $class->subject_code ?? 'N/A',
                    'subject_title' => $subjectTitle,
                    'section' => $class->section ?? 'N/A',
                    'faculty_name' => $class->faculty?->full_name ?? 'TBA',
                    'schedule' => $schedule,
                    'room' => $class->room?->name ?? 'TBA',
                    'units' => $units,
                    'grades' => [
                        'prelim' => $enrollment->prelim_grade,
                        'midterm' => $enrollment->midterm_grade,
                        'finals' => $published['finals_grade'] ?? null,
                        'average' => $published['total_average'] ?? null,
                    ],
                ];
            }

            $studentData['enrolled_classes'] = $enrolledClasses;
            $studentData['total_units'] = $totalUnits;

            // Get current tuition balance
            $currentTuition = $student->getCurrentTuitionModel();
            $studentData['tuition_balance'] = $currentTuition?->total_balance ?? 0;

            // Get clearance status
            $studentData['clearance_status'] = $settings !== null
                ? $gradeReleases->isClearedForPeriod($student, $settings->getSchoolYearString(), (int) $settings->semester)
                : null;

            // Surface active administrative holds so students can resolve blockers proactively
            $studentData['active_holds'] = app(StudentHoldService::class)
                ->activeHoldsForStudent((int) $student->id)
                ->map(fn ($hold): array => [
                    'id' => $hold->id,
                    'office' => $hold->office,
                    'title' => $hold->title ?? ucfirst(str_replace('_', ' ', (string) $hold->office)).' Hold',
                    'reason' => $hold->reason,
                    'severity' => $hold->severity ?? 'blocking',
                    'blocks_enrollment' => (bool) ($hold->blocks_enrollment ?? true),
                    'placed_at' => $hold->placed_at?->toDateString(),
                ])
                ->values()
                ->all();

            // Surface latest enrollment pipeline status & document completeness for the student journey tracker
            $latestEnrollment = StudentEnrollment::query()
                ->where('student_id', $student->id)
                ->latest('created_at')
                ->first();

            $docLocation = $student->DocumentLocation;
            $trackedDocFields = [
                'birth_certificate' => 'Birth Certificate',
                'form_138' => 'Form 138 / Report Card',
                'good_moral_cert' => 'Good Moral Certificate',
                'picture_1x1' => '1x1 ID Photo',
            ];
            $uploadedDocs = 0;
            $missingDocs = [];
            foreach ($trackedDocFields as $field => $label) {
                $val = $docLocation?->getAttribute($field);
                if (is_string($val) && mb_trim($val) !== '') {
                    $uploadedDocs++;
                } else {
                    $missingDocs[] = $label;
                }
            }

            $studentData['document_summary'] = [
                'uploaded' => $uploadedDocs,
                'total' => count($trackedDocFields),
                'missing' => $missingDocs,
            ];

            $studentData['enrollment_journey'] = [
                'student_status' => is_object($student->status) ? $student->status->value : (string) ($student->status ?? 'enrolled'),
                'latest_status' => $latestEnrollment?->status,
                'school_year' => $latestEnrollment?->school_year ?? $studentData['school_year'],
                'semester' => $latestEnrollment?->semester ?? $studentData['semester'],
                'has_current_enrollment' => $latestEnrollment !== null
                    && $latestEnrollment->school_year === $studentData['school_year']
                    && (int) $latestEnrollment->semester === (int) $studentData['semester'],
            ];
        }

        $studentData['announcements'] = app(AnnouncementDataService::class)->getDashboardItems();

        // Generate ID card data
        $idCardService = app(DigitalIdCardService::class);
        $idCardData = $idCardService->generateIdCardForUser($user);

        $isNewStudentUser = $user->isNewToOnboarding('student');
        $profileCompletionPrompt = $this->profileCompletionPrompt($user, $student);

        return Inertia::render('student/dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->value ?? 'student',
            ],
            'student_data' => $studentData,
            'id_card' => $idCardData,
            'is_new_user' => $isNewStudentUser,
            'profile_completion_prompt' => $profileCompletionPrompt,
        ]);
    }

    /**
     * @return array{total: int, completed: int, percentage: int, missing: array<int, array{key: string, label: string, section: string, example?: string}>, link: string}|null
     */
    private function profileCompletionPrompt(User $user, ?Student $student): ?array
    {
        if (! Feature::for($user)->active(StudentInformationUpdates::class) || ! $student || $student->user_id !== $user->id) {
            return null;
        }

        $completion = app(StudentProfileCompletionService::class)->summarize($student);

        if ($completion['missing'] === []) {
            return null;
        }

        $section = $completion['missing'][0]['section'] ?? 'personal';

        return [
            ...$completion,
            'missing' => array_slice($completion['missing'], 0, 3),
            'link' => "/student/profile?guided=1#student-{$section}",
        ];
    }

    /**
     * Format class schedule from class model
     */
    private function formatSchedule(mixed $class): string
    {
        // Reuse eager-loaded schedules relationship first to avoid per-class N+1 queries
        $schedules = null;

        if (is_object($class) && method_exists($class, 'relationLoaded')) {
            if ($class->relationLoaded('schedules')) {
                $schedules = $class->schedules;
            } elseif ($class->relationLoaded('Schedule')) {
                $schedules = $class->Schedule;
            } elseif (method_exists($class, 'schedules')) {
                $schedules = $class->schedules;
            } elseif (method_exists($class, 'Schedule')) {
                $schedules = $class->Schedule;
            }
        }

        if ($schedules !== null && $schedules->isNotEmpty()) {
            // Group by time to combine days (e.g. Mon/Wed 10:00-11:30)
            $groups = $schedules->groupBy(function ($sched): string {
                $start = $sched->start_time instanceof DateTimeInterface ? $sched->start_time->format('H:i') : mb_substr((string) $sched->start_time, 0, 5);
                $end = $sched->end_time instanceof DateTimeInterface ? $sched->end_time->format('H:i') : mb_substr((string) $sched->end_time, 0, 5);

                return "$start-$end";
            });

            $parts = [];
            foreach ($groups as $scheds) {
                // Collect unique days
                $days = $scheds->pluck('day_of_week')
                    ->map(
                        // Standardize to 3 letters: Mon, Tue, Wed

                        fn ($day): string => ucfirst(mb_strtolower(mb_substr((string) $day, 0, 3))))
                    ->unique()
                    // Sort days: Mon=1, Tue=2, etc. for nice display order
                    ->sortBy(fn ($day): string => date('N', strtotime((string) $day)))
                    ->values()
                    ->all();

                $dayStr = implode('/', $days);

                $first = $scheds->first();
                $startTime = $first->start_time instanceof DateTimeInterface ? $first->start_time->format('g:i A') : date('g:i A', strtotime((string) $first->start_time));
                $endTime = $first->end_time instanceof DateTimeInterface ? $first->end_time->format('g:i A') : date('g:i A', strtotime((string) $first->end_time));

                $parts[] = "$dayStr $startTime - $endTime";
            }

            return implode(', ', $parts);
        }

        // Legacy Fallback
        $days = $class->days ?? $class->day ?? null;
        $startTime = $class->start_time ?? null;
        $endTime = $class->end_time ?? null;

        if (! $days || ! $startTime) {
            return 'TBA';
        }

        // Format days
        $dayAbbreviations = is_array($days) ? implode('/', $days) : $days;

        // Format time
        $timeFormat = '';
        if ($startTime) {
            $timeFormat = date('g:i A', strtotime((string) $startTime));
            if ($endTime) {
                $timeFormat .= ' - '.date('g:i A', strtotime((string) $endTime));
            }
        }

        return sprintf('%s %s', $dayAbbreviations, $timeFormat);
    }
}
