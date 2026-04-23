<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Classes;
use App\Models\User;
use App\Services\PdfGenerationService;
use App\Support\StreamedStorage;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GenerateAttendancePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $classId,
        public int $userId,
    ) {
        $this->onQueue('pdf-generation');
    }

    public function handle(PdfGenerationService $pdfService): void
    {
        $startedAt = microtime(true);

        $class = Classes::query()
            ->with([
                'attendanceSessions.records.student',
                'class_enrollments.student',
                'subject',
                'SubjectByCodeFallback',
                'ShsSubject',
            ])
            ->findOrFail($this->classId);

        $primarySubject = $class->subjects->first();
        if (! $primarySubject) {
            $primarySubject = $class->isShs()
                ? $class->ShsSubject
                : ($class->subject ?: $class->SubjectByCodeFallback);
        }

        $sessions = $class->attendanceSessions->sortBy('session_date');
        $enrollments = $class->class_enrollments;

        $studentData = [];
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $studentName = $student?->full_name ?? $student?->name ?? 'Student #'.$enrollment->student_id;
            $studentId = $student?->student_id ?? 'N/A';

            $studentData[$enrollment->id] = [
                'name' => $studentName,
                'student_id' => $studentId,
                'attendance' => [],
                'summary' => ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0],
            ];
        }

        foreach ($sessions as $session) {
            foreach ($session->records as $record) {
                if (! isset($studentData[$record->class_enrollment_id])) {
                    continue;
                }

                $status = $record->status?->value ?? $record->status ?? 'absent';
                $studentData[$record->class_enrollment_id]['attendance'][$session->id] = $status;
                $studentData[$record->class_enrollment_id]['summary'][$status] =
                    ($studentData[$record->class_enrollment_id]['summary'][$status] ?? 0) + 1;
            }
        }

        $subjectCode = $primarySubject?->code ?? $class->subject_code ?? 'N/A';
        $section = $class->section ?? 'N/A';
        $filename = sprintf(
            'attendance-%s-%s-%s.pdf',
            Str::slug($subjectCode),
            Str::slug($section),
            now()->format('Y-m-d_His')
        );

        $viewData = [
            'class' => $class,
            'subject' => $primarySubject,
            'sessions' => $sessions,
            'studentData' => $studentData,
            'generatedAt' => now()->format('F j, Y g:i A'),
        ];

        $disk = config('filesystems.default');
        $directory = 'exports/attendance/'.$this->userId;
        Storage::disk($disk)->makeDirectory($directory);

        $temporaryFilePath = tempnam(sys_get_temp_dir(), 'attendance_pdf_').'.pdf';

        try {
            $pdfService->generatePdfFromView('pdf.attendance-report', $viewData, $temporaryFilePath, [
                'landscape' => true,
                'format' => 'A4',
                'margins' => ['top' => '10mm', 'right' => '10mm', 'bottom' => '10mm', 'left' => '10mm'],
            ]);

            $storagePath = $directory.'/'.$filename;
            StreamedStorage::putFileFromPath($disk, $storagePath, $temporaryFilePath);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $outputSize = Storage::disk($disk)->size($storagePath);

            Log::info('Queued attendance PDF generated', [
                'requester_id' => $this->userId,
                'class_id' => $this->classId,
                'duration_ms' => $durationMs,
                'output_size' => $outputSize,
                'disk' => $disk,
                'path' => $storagePath,
            ]);

            $user = User::query()->find($this->userId);
            if (! $user) {
                return;
            }

            Notification::make()
                ->title('Attendance PDF Ready')
                ->body('Your attendance report has been generated and is ready to download.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Download PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(route('download.attendance-report', ['filename' => $filename], false))
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($user)
                ->send();
        } finally {
            if (file_exists($temporaryFilePath)) {
                unlink($temporaryFilePath);
            }
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('Queued attendance PDF generation failed', [
            'requester_id' => $this->userId,
            'class_id' => $this->classId,
            'error' => $exception->getMessage(),
        ]);

        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Attendance PDF Generation Failed')
            ->body('We could not generate your attendance PDF. Please try again.')
            ->danger()
            ->sendToDatabase($user)
            ->send();
    }
}
