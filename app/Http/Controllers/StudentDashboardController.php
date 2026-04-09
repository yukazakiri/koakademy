<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\DigitalIdCardService;
use App\Services\GeneralSettingsService;
use DateTimeInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Get the student record linked to this user
        $student = Student::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();

        $generalSettingsService = app(GeneralSettingsService::class);
        $settings = GeneralSetting::query()->first();

        // Default data structure
        $studentData = [
            'student_id' => $student?->student_id ?? 'N/A',
            'student_name' => $student?->full_name ?? $user->name,
            'course' => $student?->Course?->name ?? null,
            'academic_year' => $student?->academic_year ?? 1,
            'semester' => $settings?->semester ?? 1,
            'school_year' => $generalSettingsService->getCurrentSchoolYearString(),
            'enrolled_classes' => [],
            'announcements' => [],
            'total_units' => 0,
            'tuition_balance' => 0,
            'clearance_status' => false,
        ];

        if ($student) {
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

                $enrolledClasses[] = [
                    'id' => $class->id,
                    'subject_code' => $class->subject_code ?? 'N/A',
                    'subject_title' => $subjectTitle,
                    'section' => $class->section ?? 'N/A',
                    'faculty_name' => $class->faculty?->full_name ?? 'TBA',
                    'schedule' => $schedule,
                    'room' => $class->room?->name ?? 'TBA',
                    'grades' => [
                        'prelim' => $enrollment->prelim_grade,
                        'midterm' => $enrollment->midterm_grade,
                        'finals' => $enrollment->finals_grade,
                        'average' => $enrollment->total_average,
                    ],
                ];
            }

            $studentData['enrolled_classes'] = $enrolledClasses;
            $studentData['total_units'] = $totalUnits;

            // Get current tuition balance
            $currentTuition = $student->getCurrentTuitionModel();
            $studentData['tuition_balance'] = $currentTuition?->total_balance ?? 0;

            // Get clearance status
            $studentData['clearance_status'] = $student->hasCurrentClearance();
        }

        // Get announcements
        $announcements = Announcement::query()
            ->published() // Uses scopePublished: status='published' and published_at check
            ->active()    // Uses scopeActive: expires_at check
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content ?? $announcement->body ?? '',
                'date' => $announcement->created_at->format('M d, Y'),
                'type' => $announcement->type ?? 'info',
            ])
            ->toArray();

        $studentData['announcements'] = $announcements;

        // Generate ID card data
        $idCardService = app(DigitalIdCardService::class);
        $idCardData = $idCardService->generateIdCardForUser($user);

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
        ]);
    }

    /**
     * Format class schedule from class model
     */
    private function formatSchedule(mixed $class): string
    {
        // Try to get schedule from relationship first
        if (method_exists($class, 'Schedule') && $class->Schedule()->exists()) {
            $schedules = $class->Schedule;

            if ($schedules->isNotEmpty()) {
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
