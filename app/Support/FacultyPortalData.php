<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Event;
use App\Models\Faculty;
use App\Models\User;
use Carbon\Carbon;
use Modules\Announcement\Services\AnnouncementDataService;
use Spatie\Activitylog\Models\Activity;

final class FacultyPortalData
{
    /**
     * Build dashboard payload for a faculty member.
     */
    public static function build(?User $user): array
    {
        $currentDayName = Carbon::now()
            ->timezone(config('app.timezone'))
            ->format('l');

        $defaults = [
            'stats' => [],
            'upcoming_classes' => [],
            'recent_activity' => [],
            'announcements' => [],
            'weekly_schedule' => [],
            'today_schedule' => [
                'day' => $currentDayName,
                'entries' => [],
            ],
            'calendar_events' => [],
        ];

        if (! $user || ! method_exists($user, 'isFaculty') || ! $user->isFaculty()) {
            return $defaults;
        }

        $faculty = Faculty::where('email', $user->email)->first();

        if (! $faculty) {
            return $defaults;
        }

        $classesQuery = $faculty->classes()
            ->currentAcademicPeriod()
            ->with([
                'subject',
                'SubjectByCodeFallback',
                'ShsSubject',
                'Room',
                'schedules.room',
            ])
            ->withCount('class_enrollments');

        $activeClassesCount = $classesQuery->clone()->count();

        $facultyClassIds = $classesQuery->clone()->pluck('id');

        $totalStudentsCount = ClassEnrollment::whereIn(
            'class_id',
            $facultyClassIds
        )
            ->distinct('student_id')
            ->count();

        $upcomingClasses = $classesQuery
            ->clone()
            ->limit(5)
            ->get()
            ->map(function ($class): array {
                $firstSubject = $class->subjects->first();

                if (! $firstSubject) {
                    $firstSubject = $class->isShs() ? $class->ShsSubject : ($class->subject ?: $class->SubjectByCodeFallback);
                }

                return [
                    'id' => $class->id,
                    'subject_code' => $firstSubject?->code ?? $class->subject_code ?? 'N/A',
                    'subject_title' => $firstSubject?->title ?? 'N/A',
                    'section' => $class->section ?? 'N/A',
                    'school_year' => $class->school_year ?? 'N/A',
                    'semester' => $class->semester ?? 'N/A',
                    'room' => $class->Room?->name ?? 'TBA',
                    'students_count' => $class->class_enrollments_count ?? 0,
                    'classification' => $class->classification ?? 'college',
                ];
            })
            ->values()
            ->all();

        $weeklyEntriesByDay = $classesQuery
            ->clone()
            ->get()
            ->flatMap(function ($class) {
                $firstSubject = $class->subjects->first();

                if (! $firstSubject) {
                    $firstSubject = $class->isShs() ? $class->ShsSubject : ($class->subject ?: $class->SubjectByCodeFallback);
                }

                return $class->schedules->map(function ($schedule) use ($class, $firstSubject): array {
                    $day = ucfirst(mb_strtolower((string) $schedule->day_of_week ?? ''));

                    return [
                        'id' => $schedule->id,
                        'class_id' => $class->id,
                        'day' => $day,
                        'start_time' => $schedule->formatted_start_time,
                        'end_time' => $schedule->formatted_end_time,
                        'start_time_24h' => $schedule->start_time?->format('H:i'),
                        'end_time_24h' => $schedule->end_time?->format('H:i'),
                        'subject_code' => $firstSubject?->code ?? $class->subject_code ?? 'N/A',
                        'subject_title' => $firstSubject?->title ?? 'N/A',
                        'section' => $class->section ?? 'N/A',
                        'room' => $schedule->room?->name ?? $class->Room?->name ?? 'TBA',
                        'course_codes' => $class->formatted_course_codes ?? 'N/A',
                        'classification' => $class->classification ?? 'college',
                    ];
                });
            })
            ->filter(fn ($entry): bool => in_array($entry['day'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], true))
            ->groupBy('day')
            ->map(fn ($entries) => $entries
                ->sortBy('start_time_24h')
                ->values());

        $dayOrder = collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);

        $weeklySchedule = $dayOrder
            ->map(fn ($day): array => [
                'day' => $day,
                'entries' => $weeklyEntriesByDay->get($day, collect())->values()->all(),
            ])
            ->values()
            ->all();

        $todaySchedule = [
            'day' => $currentDayName,
            'entries' => $weeklyEntriesByDay->get($currentDayName, collect())->values()->all(),
        ];

        $stats = [
            [
                'label' => 'Active Classes',
                'value' => $activeClassesCount,
                'icon' => 'book',
                'trend' => '+2',
                'trendDirection' => 'up',
            ],
            [
                'label' => 'Total Students',
                'value' => $totalStudentsCount,
                'icon' => 'users',
                'trend' => '+5%',
                'trendDirection' => 'up',
            ],
            [
                'label' => 'Total Hours',
                'value' => '24',
                'icon' => 'clock',
                'trend' => '0%',
                'trendDirection' => 'neutral',
            ],
            [
                'label' => 'Performance',
                'value' => '98%',
                'icon' => 'activity',
                'trend' => '+1%',
                'trendDirection' => 'up',
            ],
        ];

        // Fetch real activities for this faculty's classes from activity log
        $recentActivity = self::getRecentActivities($facultyClassIds);

        $announcements = app(AnnouncementDataService::class)->getDashboardItems();

        // Get attendance chart data for the dashboard
        $attendanceChart = self::getAttendanceChartData($classesQuery->clone()->get());

        // Get calendar events for the dashboard widget
        $calendarEvents = self::getCalendarEvents();

        return [
            'stats' => $stats,
            'upcoming_classes' => $upcomingClasses,
            'recent_activity' => $recentActivity,
            'announcements' => $announcements,
            'weekly_schedule' => $weeklySchedule,
            'today_schedule' => $todaySchedule,
            'attendance_chart' => $attendanceChart,
            'calendar_events' => $calendarEvents,
        ];
    }

    /**
     * Get recent activities for a faculty member's classes
     *
     * @param  \Illuminate\Support\Collection  $facultyClassIds
     * @return array<int, array{action: string, target: string, time: string}>
     */
    private static function getRecentActivities($facultyClassIds): array
    {
        // Query activities related to faculty's classes
        $activities = Activity::query()
            ->whereIn('log_name', ['classes', 'class_posts', 'class_enrollments'])
            ->where(function ($query) use ($facultyClassIds): void {
                // Activities on Classes owned by this faculty
                $query->where(function ($q) use ($facultyClassIds): void {
                    $q->where('subject_type', Classes::class)
                        ->whereIn('subject_id', $facultyClassIds);
                })
                    // Activities on ClassPosts in faculty's classes
                    ->orWhere(function ($q) use ($facultyClassIds): void {
                        $q->where('subject_type', ClassPost::class)
                            ->whereIn('subject_id', function ($subQuery) use ($facultyClassIds): void {
                                $subQuery->select('id')
                                    ->from('class_posts')
                                    ->whereIn('class_id', $facultyClassIds);
                            });
                    })
                    // Activities on ClassEnrollments in faculty's classes
                    ->orWhere(function ($q) use ($facultyClassIds): void {
                        $q->where('subject_type', ClassEnrollment::class)
                            ->whereIn('subject_id', function ($subQuery) use ($facultyClassIds): void {
                                $subQuery->select('id')
                                    ->from('class_enrollments')
                                    ->whereIn('class_id', $facultyClassIds);
                            });
                    });
            })
            ->latest()
            ->take(10)
            ->get();

        return $activities->map(fn (Activity $activity): array => [
            'action' => self::formatActivityAction($activity),
            'target' => self::formatActivityTarget($activity),
            'time' => $activity->created_at->diffForHumans(),
        ])->all();
    }

    /**
     * Format the activity action for display with detailed change information
     */
    private static function formatActivityAction(Activity $activity): string
    {
        // Special handling for ClassEnrollment events
        if ($activity->subject_type === ClassEnrollment::class) {
            return self::formatEnrollmentAction($activity);
        }

        $subjectType = match ($activity->subject_type) {
            Classes::class => 'class',
            ClassPost::class => 'post',
            default => 'item',
        };

        // For updates, show what specific fields changed
        if ($activity->event === 'updated' && $activity->properties->has('attributes')) {
            $changes = self::formatChangedFields($activity);
            if ($changes) {
                return "Updated {$subjectType}: {$changes}";
            }
        }

        $eventVerb = match ($activity->event) {
            'created' => 'Created new',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            default => ucfirst($activity->event ?? 'Modified'),
        };

        return "{$eventVerb} {$subjectType}";
    }

    /**
     * Format enrollment-specific activity actions
     */
    private static function formatEnrollmentAction(Activity $activity): string
    {
        $attributes = $activity->properties->get('attributes', []);
        $oldValues = $activity->properties->get('old', []);

        // New student enrolled
        if ($activity->event === 'created') {
            return 'New student enrolled';
        }

        // Student removed from class
        if ($activity->event === 'deleted') {
            return 'Student removed from class';
        }

        // Check what changed
        if ($activity->event === 'updated') {
            // Student moved to different class/section
            if (isset($attributes['class_id']) && isset($oldValues['class_id']) && $attributes['class_id'] !== $oldValues['class_id']) {
                return 'Student moved to different section';
            }

            // Grade updates
            $gradeFields = ['prelim_grade', 'midterm_grade', 'finals_grade'];
            $gradeChanges = [];
            foreach ($gradeFields as $field) {
                if (isset($attributes[$field])) {
                    $label = match ($field) {
                        'prelim_grade' => 'Prelim',
                        'midterm_grade' => 'Midterm',
                        'finals_grade' => 'Finals',
                    };
                    $oldVal = $oldValues[$field] ?? 'none';
                    $newVal = $attributes[$field] ?? 'none';
                    $gradeChanges[] = "{$label}: {$oldVal} → {$newVal}";
                }
            }

            if ($gradeChanges !== []) {
                return 'Updated grades: '.implode(', ', array_slice($gradeChanges, 0, 2));
            }

            // Grades finalized
            if (isset($attributes['is_grades_finalized']) && $attributes['is_grades_finalized']) {
                return 'Finalized student grades';
            }

            // Status change
            if (isset($attributes['status'])) {
                return 'Updated enrollment status';
            }
        }

        return 'Updated enrollment';
    }

    /**
     * Format changed fields into a readable string
     */
    private static function formatChangedFields(Activity $activity): ?string
    {
        $attributes = $activity->properties->get('attributes', []);
        $oldValues = $activity->properties->get('old', []);

        if (empty($attributes)) {
            return null;
        }

        // Field labels for better readability
        $fieldLabels = [
            'section' => 'Section',
            'faculty_id' => 'Faculty',
            'maximum_slots' => 'Max slots',
            'semester' => 'Semester',
            'school_year' => 'School year',
            'subject_code' => 'Subject',
            'prelim_grade' => 'Prelim grade',
            'midterm_grade' => 'Midterm grade',
            'finals_grade' => 'Finals grade',
            'is_grades_finalized' => 'Grades finalized',
            'status' => 'Status',
            'title' => 'Title',
            'type' => 'Type',
        ];

        $changes = [];
        foreach ($attributes as $field => $newValue) {
            $label = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $oldValue = $oldValues[$field] ?? null;

            // Format boolean values
            if (is_bool($newValue)) {
                $newValue = $newValue ? 'Yes' : 'No';
            }
            if (is_bool($oldValue)) {
                $oldValue = $oldValue ? 'Yes' : 'No';
            }

            // Format null values
            $oldValue ??= 'none';
            $newValue ??= 'none';

            if ($oldValue !== $newValue) {
                $changes[] = "{$label}: {$oldValue} → {$newValue}";
            }
        }

        return $changes === [] ? null : implode(', ', array_slice($changes, 0, 2));
    }

    /**
     * Format the activity target for display with context
     */
    private static function formatActivityTarget(Activity $activity): string
    {
        // For Classes - show subject code and section
        if ($activity->subject_type === Classes::class) {
            if ($activity->subject) {
                $class = $activity->subject;

                return sprintf(
                    '%s - %s',
                    $class->subject_code ?? 'Unknown Subject',
                    $class->section ?? 'No Section'
                );
            }

            return 'Class record';
        }

        // For ClassPost - show post title and class context
        if ($activity->subject_type === ClassPost::class) {
            if ($activity->subject) {
                $post = $activity->subject;
                $classInfo = $post->class ? "{$post->class->subject_code}" : '';

                return sprintf(
                    '"%s"%s',
                    mb_strlen((string) $post->title) > 30 ? mb_substr((string) $post->title, 0, 30).'...' : $post->title,
                    $classInfo !== '' && $classInfo !== '0' ? " in {$classInfo}" : ''
                );
            }

            return 'Post record';
        }

        // For ClassEnrollment - show student name and class
        if ($activity->subject_type === ClassEnrollment::class) {
            if ($activity->subject) {
                $enrollment = $activity->subject;
                $student = $enrollment->student;
                $class = $enrollment->class;

                $studentName = $student
                    ? "{$student->first_name} {$student->last_name}"
                    : 'Unknown student';

                $classInfo = $class
                    ? " ({$class->subject_code})"
                    : '';

                return "{$studentName}{$classInfo}";
            }

            return 'Enrollment record';
        }

        // Fallback to description
        if ($activity->description) {
            return $activity->description;
        }

        return 'Unknown';
    }

    /**
     * Get attendance chart data for the dashboard
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Classes>  $classes
     * @return array{chart_data: array<int, array{date: string, present: int, absent: int, late: int, excused: int}>, classes: array<int, array{id: int, label: string}>}
     */
    private static function getAttendanceChartData($classes): array
    {
        $classIds = $classes->pluck('id');

        // Get attendance sessions for the last 3 months (to support all time range filters)
        $threeMonthsAgo = Carbon::now()->subMonths(3)->startOfDay();

        $sessions = ClassAttendanceSession::whereIn('class_id', $classIds)
            ->where('session_date', '>=', $threeMonthsAgo)
            ->where('is_no_meeting', false)
            ->with(['records'])
            ->get();

        // Group by date and calculate attendance stats
        $chartData = $sessions
            ->groupBy(fn (ClassAttendanceSession $session): string => $session->session_date->format('Y-m-d'))
            ->map(function ($daySessions, string $date): array {
                $records = $daySessions->flatMap(fn ($s) => $s->records);

                return [
                    'date' => $date,
                    'present' => $records->filter(fn ($r): bool => $r->status?->value === 'present')->count(),
                    'absent' => $records->filter(fn ($r): bool => $r->status?->value === 'absent')->count(),
                    'late' => $records->filter(fn ($r): bool => $r->status?->value === 'late')->count(),
                    'excused' => $records->filter(fn ($r): bool => $r->status?->value === 'excused')->count(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        // Build class list for filter dropdown
        $classList = $classes->map(function (Classes $class): array {
            $subject = $class->subjects->first() ?? $class->Subject ?? $class->SubjectByCodeFallback;

            return [
                'id' => $class->id,
                'label' => sprintf(
                    '%s - %s',
                    $subject?->code ?? $class->subject_code ?? 'Unknown',
                    $class->section ?? 'N/A'
                ),
            ];
        })->values()->all();

        return [
            'chart_data' => $chartData,
            'classes' => $classList,
        ];
    }

    /**
     * Get calendar events for the dashboard widget.
     *
     * @return array<int, array{id: int, title: string, description: string|null, location: string|null, start_datetime: string, end_datetime: string|null, is_all_day: bool, type: string, category: string, status: string, color: string}>
     */
    private static function getCalendarEvents(): array
    {
        $now = Carbon::now();
        $twoMonthsFromNow = Carbon::now()->addMonths(2);

        return Event::query()
            ->active()
            ->public()
            ->where('start_datetime', '>=', $now)
            ->where('start_datetime', '<=', $twoMonthsFromNow)
            ->orderBy('start_datetime')
            ->limit(20)
            ->get()
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'start_datetime' => $event->start_datetime->toIso8601String(),
                'end_datetime' => $event->end_datetime?->toIso8601String(),
                'is_all_day' => $event->is_all_day,
                'type' => $event->type ?? 'other',
                'category' => $event->category ?? 'academic',
                'status' => $event->status,
                'color' => self::getEventColor($event->type, $event->category),
            ])
            ->all();
    }

    /**
     * Get the color for an event based on type and category.
     */
    private static function getEventColor(?string $type, ?string $category): string
    {
        return match ($type) {
            'academic_calendar' => '#10b981',
            'resource_booking' => '#f59e0b',
            default => match ($category) {
                'academic' => '#3b82f6',
                'administrative' => '#8b5cf6',
                'extracurricular' => '#ec4899',
                'social' => '#6366f1',
                'sports' => '#ef4444',
                'cultural' => '#f97316',
                'holiday' => '#22c55e',
                default => '#6b7280',
            },
        };
    }
}
