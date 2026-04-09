<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Student;
use App\Models\User;
use App\Notifications\TimetablePdfFailedNotification;
use App\Notifications\TimetablePdfGeneratedNotification;
use App\Services\PdfGenerationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateStudentTimetablePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 300; // 5 minutes timeout

    public $tries = 3;

    private PdfGenerationService $pdfService;

    public function __construct(
        public Student $student
    ) {
        $this->pdfService = app(PdfGenerationService::class);
        $this->onQueue('default');
    }

    public function handle(): void
    {
        try {
            Log::info('Starting student timetable PDF generation', [
                'student_id' => $this->student->id,
                'student_name' => $this->student->full_name ?? 'Unknown',
            ]);

            // Generate PDF using direct Browsershot approach (same as working GenerateStudentListPdfJob)
            $filename = $this->generateTimetablePdf($this->student);

            Log::info('Student timetable PDF generated successfully', [
                'student_id' => $this->student->id,
                'filename' => $filename,
            ]);

            // Send success notifications
            $this->sendSuccessNotification($filename);

        } catch (Exception $exception) {
            Log::error('Failed to generate student timetable PDF', [
                'student_id' => $this->student->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Send failure notifications
            $this->sendFailureNotification($exception->getMessage());

            throw $exception;
        }
    }

    public function failed(Throwable $throwable): void
    {
        Log::error('Student timetable PDF job failed permanently', [
            'student_id' => $this->student->id,
            'error' => $throwable->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Send failure notification when job fails permanently
        $this->sendFailureNotification($throwable->getMessage());
    }

    /**
     * Generate timetable PDF using direct Browsershot approach
     */
    private function generateTimetablePdf(Student $student): string
    {
        // Get student schedules through class enrollments
        // Get all class IDs for the student
        $studentClassIds = $student->classEnrollments()->pluck('class_id');

        // Query schedules for all the student's classes
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

        if ($schedules->isEmpty()) {
            throw new Exception('No schedules found for this student.');
        }

        // Get the academic year and semester from the first schedule's class
        $firstSchedule = $schedules->first();
        $currentSchoolYear = $firstSchedule->class->school_year;
        $currentSemester = $firstSchedule->class->semester;

        // Prepare data for view - match the expected structure
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $timeSlots = $this->getTimeSlots();

        // Build the timetable grid
        $timetableGrid = [];
        foreach ($timeSlots as $timeSlot) {
            $row = ['time' => $timeSlot];
            foreach ($days as $day) {
                $row[$day] = null; // Initialize as null
            }
            $timetableGrid[] = $row;
        }

        // Populate the grid with schedules
        foreach ($schedules as $schedule) {
            $dayName = $this->getDayName($schedule->day_of_week);
            $startTime = $schedule->start_time->format('H:i');
            $endTime = $schedule->end_time->format('H:i');

            // Find the time slot index by matching the start time with time slot ranges
            $timeSlotIndex = $this->findTimeSlotIndex($startTime, $timeSlots);
            if ($timeSlotIndex !== false && isset($timetableGrid[$timeSlotIndex])) {
                // Calculate span (how many time slots this schedule covers)
                $span = $this->calculateScheduleSpan($schedule, $timeSlots);

                // Generate color for this class
                $color = $this->generateClassColor($schedule->class_id);

                $timetableGrid[$timeSlotIndex][$dayName] = [
                    'subject_code' => $schedule->class->subject->code ?? 'N/A',
                    'subject_title' => $schedule->class->subject->title ?? 'N/A',
                    'start_time' => $startTime.' - '.$endTime,
                    'room' => $schedule->room->room_number ?? 'TBD',
                    'span' => $span,
                    'color' => $color,
                ];
            }
        }

        // Prepare classes list
        $classes = $schedules->map(fn ($schedule): array => [
            'subject_code' => $schedule->class->subject->code ?? 'N/A',
            'subject_title' => $schedule->class->subject->title ?? 'N/A',
            'section' => $schedule->class->section ?? 'N/A',
            'instructor' => $schedule->class->faculty->full_name ?? 'TBD',
            'units' => $schedule->class->subject->units ?? 0,
            'classification' => 'Lecture', // You might want to determine this from class data
        ])->unique('subject_code');

        // Prepare student data
        $studentData = [
            'student_id' => $student->student_id,
            'full_name' => $student->full_name,
            'email' => $student->email ?? 'N/A',
            'phone' => $student->phone ?? 'N/A',
            'course' => $student->course->code ?? 'N/A',
            'birth_date' => $student->birth_date->format('M d, Y') ?? 'N/A',
            'gender' => $student->gender ?? 'N/A',
            'address' => $student->address ?? 'N/A',
        ];

        // Prepare timetable data
        $timetable = [
            'school_year' => $currentSchoolYear,
            'semester' => $currentSemester,
            'days' => $days,
            'grid' => $timetableGrid,
        ];

        $viewData = [
            'student' => $studentData,
            'classes' => $classes,
            'timetable' => $timetable,
        ];

        // Generate filename
        $studentName = preg_replace('/[^a-zA-Z0-9]/', '_', (string) $student->full_name);
        $filename = "timetable_{$studentName}_".date('Y-m-d_H-i-s').'.pdf';

        // Use the configured filesystem disk
        $disk = config('filesystems.default');
        $directory = 'schedules';

        // Ensure directory exists
        Storage::disk($disk)->makeDirectory($directory);

        // Generate PDF to temporary file first
        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';
        $this->pdfService->generatePdfFromView('pdf.student-timetable', $viewData, $tempPath);

        // Upload to configured storage
        $storagePath = $directory.'/'.$filename;
        Storage::disk($disk)->put($storagePath, file_get_contents($tempPath));

        // Clean up temporary file
        unlink($tempPath);

        Log::info('PDF uploaded to storage', [
            'disk' => $disk,
            'path' => $storagePath,
            'filename' => $filename,
        ]);

        return $filename;
    }

    /**
     * Get time slots for timetable
     */
    private function getTimeSlots(): array
    {
        return [
            '07:00-08:00',
            '08:00-09:00',
            '09:00-10:00',
            '10:00-11:00',
            '11:00-12:00',
            '12:00-13:00',
            '13:00-14:00',
            '14:00-15:00',
            '15:00-16:00',
            '16:00-17:00',
            '17:00-18:00',
            '18:00-19:00',
            '19:00-20:00',
        ];
    }

    /**
     * Find time slot index for a given start time
     */
    private function findTimeSlotIndex(string $startTime, array $timeSlots): int|string|false
    {
        foreach ($timeSlots as $index => $timeSlot) {
            // Extract start time from the time slot range (e.g., "07:00-08:00" -> "07:00")
            $slotStartTime = explode('-', (string) $timeSlot)[0];
            if ($slotStartTime === $startTime) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Send success notification to all admin users
     */
    private function sendSuccessNotification(string $filename): void
    {
        try {
            $studentName = $this->student->full_name ?? 'Unknown Student';
            $downloadUrl = route('download.timetable-pdf', ['filename' => $filename]);

            // Get all admin users
            $adminUsers = User::role(['admin', 'super_admin'])->get();

            foreach ($adminUsers as $user) {
                // Send database notification
                $user->notify(new TimetablePdfGeneratedNotification(
                    $filename,
                    $studentName,
                    $downloadUrl
                ));

                // Also send real-time Filament notification
                $notification = new TimetablePdfGeneratedNotification(
                    $filename,
                    $studentName,
                    $downloadUrl
                );
                $notification->sendFilamentNotification($user);
            }

            Log::info('Success notifications sent', [
                'filename' => $filename,
                'student_name' => $studentName,
                'admin_count' => $adminUsers->count(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send success notification', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
        }
    }

    /**
     * Send failure notification to all admin users
     */
    private function sendFailureNotification(string $errorMessage): void
    {
        try {
            $studentName = $this->student->full_name ?? 'Unknown Student';

            // Get all admin users
            $adminUsers = User::role(['admin', 'super_admin'])->get();

            foreach ($adminUsers as $user) {
                // Send database notification
                $user->notify(new TimetablePdfFailedNotification(
                    $studentName,
                    $errorMessage
                ));

                // Also send real-time Filament notification
                $notification = new TimetablePdfFailedNotification(
                    $studentName,
                    $errorMessage
                );
                $notification->sendFilamentNotification($user);
            }

            Log::info('Failure notifications sent', [
                'student_name' => $studentName,
                'error_message' => $errorMessage,
                'admin_count' => $adminUsers->count(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send failure notification', [
                'error' => $e->getMessage(),
                'original_error' => $errorMessage,
            ]);
        }
    }

    /**
     * Get day name from day number or string
     */
    private function getDayName(int|string $day): string
    {
        $dayMap = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            'Monday' => 'Monday',
            'Tuesday' => 'Tuesday',
            'Wednesday' => 'Wednesday',
            'Thursday' => 'Thursday',
            'Friday' => 'Friday',
            'Saturday' => 'Saturday',
            'Sunday' => 'Sunday',
        ];

        return $dayMap[$day] ?? 'Monday';
    }

    /**
     * Calculate how many time slots a schedule spans
     */
    private function calculateScheduleSpan($schedule, array $timeSlots): int
    {
        $startTime = $schedule->start_time->format('H:i');
        $endTime = $schedule->end_time->format('H:i');

        $startIndex = $this->findTimeSlotIndex($startTime, $timeSlots);
        $endIndex = $this->findTimeSlotIndex($endTime, $timeSlots);

        if ($startIndex === false || $endIndex === false) {
            return 1;
        }

        return max(1, $endIndex - $startIndex);
    }

    /**
     * Generate a consistent color for a class
     */
    private function generateClassColor(int|string $classId): string
    {
        $colors = [
            '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
            '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16',
            '#06b6d4', '#a855f7', '#f43f5e', '#22c55e', '#eab308',
        ];

        return $colors[abs(crc32((string) $classId)) % count($colors)];
    }
}
