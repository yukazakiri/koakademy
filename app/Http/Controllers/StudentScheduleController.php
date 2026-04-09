<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentScheduleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Get the student record linked to this user
        $student = Student::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->with(['Course'])
            ->first();

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->getFilamentAvatarUrl(),
            'role' => $user->role?->value ?? 'student',
        ];

        if (! $student) {
            return Inertia::render('student/schedule', [
                'user' => $userData,
                'faculty_data' => [
                    'classes' => [],
                    'stats' => [],
                ],
                'rooms' => [],
            ]);
        }

        // --- Fetch Current Classes (Detailed for Schedule) ---
        $currentClasses = [];
        $currentEnrollments = $student->getCurrentClasses();

        foreach ($currentEnrollments as $ce) {
            $class = $ce->class;
            if (! $class) {
                continue;
            }

            $subject = $class->subject
                ?? $class->subjectByCode
                ?? $class->subjectByCodeFallback
                ?? $class->shsSubject;

            // Format schedule
            $schedule = 'TBA';
            $days = $class->days ?? $class->day ?? null;
            $startTime = $class->start_time ?? null;

            if ($days && $startTime) {
                $dayAbbreviations = is_array($days) ? implode('/', $days) : $days;
                $timeFormat = date('g:i A', strtotime((string) $startTime));
                if ($class->end_time) {
                    $timeFormat .= ' - '.date('g:i A', strtotime((string) $class->end_time));
                }
                $schedule = sprintf('%s %s', $dayAbbreviations, $timeFormat);
            }

            // Load schedules relationship if not already loaded
            if (! $class->relationLoaded('schedules')) {
                $class->load('schedules');
            }

            $currentClasses[] = [
                'id' => $class->id,
                'subject_code' => $class->subject_code ?? 'N/A',
                'subject_title' => $subject?->title ?? $class->subject_title ?? 'Unknown Subject',
                'section' => $class->section ?? 'N/A',
                'units' => $subject?->units ?? 0,
                'faculty_name' => $class->faculty?->full_name ?? 'TBA',
                'schedule' => $schedule,
                'room' => $class->room?->name ?? 'TBA',
                'room_id' => $class->room_id,
                'faculty_id' => $class->faculty_id,
                'maximum_slots' => $class->maximum_slots,
                'students_count' => $class->class_enrollments_count ?? 0,
                'classification' => $class->classification,
                'strand_id' => $class->shs_strand_id,
                'subject_id' => $class->subject_id,
                'semester' => $class->semester,
                'school_year' => $class->school_year,
                'schedules' => $class->schedules->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'room_id' => $schedule->room_id,
                ]),
                'settings' => $class->settings,
                'accent_color' => $class->settings['accent_color'] ?? null,
                'background_color' => $class->settings['background_color'] ?? null,
            ];
        }

        // Get Rooms for filters
        $rooms = \App\Models\Room::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('student/schedule', [
            'user' => $userData,
            'faculty_data' => [
                'classes' => $currentClasses,
                'stats' => [],
            ],
            'rooms' => $rooms,
        ]);
    }
}
