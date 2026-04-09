<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

final class StudentTimetablePdfService
{
    public function generateTimetablePdf(Student $student): string
    {
        try {
            Log::info('Starting student timetable PDF generation', [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
            ]);

            $filename = $this->generateFilename($student);

            // Get student data
            $studentData = $this->getStudentData($student);
            $classData = $this->getClassData($student);
            $timetableData = $this->getTimetableData($student);

            Log::info('Student timetable data collected', [
                'student_id' => $student->id,
                'class_count' => $classData->count(),
                'has_timetable_data' => ! empty($timetableData['grid']),
            ]);

            // Generate HTML
            $html = View::make('pdf.student-timetable', [
                'student' => $studentData,
                'classes' => $classData,
                'timetable' => $timetableData,
            ])->render();

            // Generate PDF using the working direct approach
            try {
                Log::info('Attempting PDF generation with full options', [
                    'student_id' => $student->id,
                ]);

                $pdf = Browsershot::html($html)
                    ->setChromePath('/usr/bin/google-chrome-stable')
                    ->noSandbox()
                    ->disableSetuidSandbox()
                    ->disableDevShmUsage()
                    ->disableGpu()
                    ->format('A4')
                    ->landscape(true)
                    ->margins(8, 8, 8, 8)
                    ->showBackground()
                    ->timeout(120)
                    ->waitUntilNetworkIdle()
                    ->pdf();
            } catch (Exception $e) {
                Log::warning('PDF generation with full options failed, using fallback', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);

                // Fallback: try without additional options
                $pdf = Browsershot::html($html)
                    ->setChromePath('/usr/bin/google-chrome-stable')
                    ->noSandbox()
                    ->disableSetuidSandbox()
                    ->disableDevShmUsage()
                    ->disableGpu()
                    ->format('A4')
                    ->landscape(true)
                    ->margins(8, 8, 8, 8)
                    ->showBackground()
                    ->timeout(120)
                    ->pdf();
            }

            // Store PDF in MinIO (default filesystem)
            $path = 'pdfs/'.$filename;
            Storage::put($path, $pdf);

            Log::info('Student timetable PDF generated successfully', [
                'student_id' => $student->id,
                'filename' => $filename,
                'path' => $path,
                'pdf_size' => mb_strlen((string) $pdf),
            ]);

            return $filename;
        } catch (Exception $exception) {
            Log::error('Failed to generate student timetable PDF', [
                'student_id' => $student->id,
                'student_name' => $student->full_name ?? 'N/A',
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function generateFilename(Student $student): string
    {
        return 'timetable_'.$student->student_id.'_'.date('Y-m-d_H-i-s').'.pdf';
    }

    private function getStudentData(Student $student): array
    {
        return [
            'student_id' => $student->student_id,
            'full_name' => $student->full_name,
            'email' => $student->email,
            'phone' => $student->studentContactsInfo->personal_contact ?? 'N/A',
            'course' => $student->Course->code ?? 'N/A',
            'birth_date' => $student->birth_date->format('F d, Y'),
            'gender' => ucfirst($student->gender),
            'address' => $student->personalInfo->current_adress ?? 'N/A',
        ];
    }

    private function getClassData(Student $student): Collection
    {
        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        $schoolYearWithSpaces = $currentSchoolYear;
        $schoolYearNoSpaces = str_replace(' ', '', $currentSchoolYear);

        return $student->classEnrollments()
            ->whereHas('class', function ($query) use ($schoolYearWithSpaces, $schoolYearNoSpaces, $currentSemester): void {
                $query->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                    ->where('semester', $currentSemester);
            })
            ->with(['class.subject', 'class.faculty'])
            ->get()
            ->map(function ($enrollment): array {
                $class = $enrollment->class;

                return [
                    'subject_code' => $class->subject_code ?? 'N/A',
                    'subject_title' => $class->subject_title ?? 'N/A',
                    'section' => $class->section ?? 'N/A',
                    'instructor' => $class->faculty?->full_name ?? 'N/A',
                    'units' => $class->subject?->units ?? $class->shsSubject?->units ?? 'N/A',
                    'classification' => mb_strtoupper($class->classification ?? 'N/A'),
                ];
            });
    }

    private function getTimetableData(Student $student): array
    {
        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        $schoolYearWithSpaces = $currentSchoolYear;
        $schoolYearNoSpaces = str_replace(' ', '', $currentSchoolYear);

        // Get schedules
        $studentClassIds = $student->classEnrollments()
            ->whereHas('class', function ($query) use ($schoolYearWithSpaces, $schoolYearNoSpaces, $currentSemester): void {
                $query->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                    ->where('semester', $currentSemester);
            })
            ->pluck('class_id');

        $schedules = \App\Models\Schedule::query()
            ->whereIn('class_id', $studentClassIds)
            ->with([
                'class.subject',
                'class.faculty',
                'room',
            ])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $filteredTimes = $this->generateTimeSlots($schedules);
        $days = $this->getDays();

        // Build timetable grid
        $grid = [];
        foreach ($filteredTimes as $timeSlot) {
            $row = ['time' => $timeSlot];
            foreach ($days as $day) {
                $schedule = $schedules->first(function ($sched) use ($day, $timeSlot): bool {
                    if (mb_strtolower((string) $sched->day_of_week) !== mb_strtolower((string) $day)) {
                        return false;
                    }

                    $slotTime = \Carbon\Carbon::parse($timeSlot);
                    $scheduleStart = $sched->start_time;
                    $scheduleEnd = $sched->end_time;
                    if ($slotTime->between($scheduleStart, $scheduleEnd, false)) {
                        return true;
                    }

                    return $slotTime->eq($scheduleStart);
                });

                if ($schedule) {
                    $slotTime = \Carbon\Carbon::parse($timeSlot);
                    if ($slotTime->eq($schedule->start_time)) {
                        // Calculate span
                        $span = $this->calculateSlotDuration($schedule->start_time, $schedule->end_time);

                        $row[$day] = [
                            'subject_code' => $schedule->class->subject_code ?? 'N/A',
                            'subject_title' => $schedule->class->subject_title ?? 'N/A',
                            'room' => $schedule->room?->name ?? 'N/A',
                            'instructor' => $schedule->class->faculty?->full_name ?? 'N/A',
                            'start_time' => $schedule->start_time->format('g:i A'),
                            'end_time' => $schedule->end_time->format('g:i A'),
                            'span' => $span,
                            'color' => $this->generateClassColor($schedule->class_id),
                        ];
                    } else {
                        $row[$day] = null; // Part of a multi-slot class
                    }
                } else {
                    $row[$day] = null;
                }
            }
            $grid[] = $row;
        }

        return [
            'grid' => $grid,
            'days' => $days,
            'time_slots' => $filteredTimes,
            'school_year' => $currentSchoolYear,
            'semester' => $currentSemester,
        ];
    }

    private function generateTimeSlots(Collection $schedules): array
    {
        // Generate time slots (8 AM - 6 PM)
        $timeSlots = [];
        for ($hour = 8; $hour <= 18; $hour++) {
            $timeSlots[] = sprintf('%02d:00', $hour);
            if ($hour < 18) {
                $timeSlots[] = sprintf('%02d:30', $hour);
            }
        }

        // Filter to only include time slots that are actually used
        $filteredTimes = [];
        foreach ($timeSlots as $time) {
            $hasSchedule = $schedules->contains(function ($schedule) use ($time): bool {
                $slotTime = \Carbon\Carbon::parse($time);
                $scheduleStart = $schedule->start_time;
                $scheduleEnd = $schedule->end_time;
                if ($slotTime->between($scheduleStart, $scheduleEnd, false)) {
                    return true;
                }

                return $slotTime->eq($scheduleStart);
            });

            if ($hasSchedule) {
                $filteredTimes[] = $time;
            }
        }

        // Add padding
        if ($filteredTimes !== []) {
            $firstTime = \Carbon\Carbon::parse($filteredTimes[0]);
            $lastTime = \Carbon\Carbon::parse($filteredTimes[count($filteredTimes) - 1]);

            $paddingStart = $firstTime->copy()->subMinutes(30);
            if ($paddingStart->format('H:i') >= '08:00') {
                array_unshift($filteredTimes, $paddingStart->format('H:i'));
            }

            $paddingEnd = $lastTime->copy()->addMinutes(30);
            if ($paddingEnd->format('H:i') <= '18:30') {
                $filteredTimes[] = $paddingEnd->format('H:i');
            }
        }

        return $filteredTimes;
    }

    private function getDays(): array
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    }

    private function calculateSlotDuration($startTime, $endTime): int
    {
        if (is_string($startTime)) {
            $startTime = \Carbon\Carbon::parse($startTime);
        }
        if (is_string($endTime)) {
            $endTime = \Carbon\Carbon::parse($endTime);
        }

        $durationMinutes = $startTime->diffInMinutes($endTime);

        return max(1, (int) ($durationMinutes / 30));
    }

    private function generateClassColor(int $classId): string
    {
        $hash = crc32((string) $classId);
        $colors = [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#6B7280',
        ];

        return $colors[abs($hash) % count($colors)];
    }
}
