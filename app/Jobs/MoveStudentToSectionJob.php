<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClassEnrollment;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\StudentSectionTransferService;
use App\Services\StudentTransferEmailService;
use Exception;
// use Filament\Notifications\Actions\Action;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background job for moving a single student to a different section
 */
final class MoveStudentToSectionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120; // 2 minutes timeout

    public int $tries = 3;

    public int $maxExceptions = 2;

    private string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $classEnrollmentId,
        private int $newClassId,
        private ?int $initiatedByUserId = null,
        private bool $notifyStudent = true,
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?? uniqid('move_student_', true);
        $this->onQueue('student-transfers');

        Log::info('MoveStudentToSectionJob created', [
            'job_id' => $this->jobId,
            'class_enrollment_id' => $this->classEnrollmentId,
            'new_class_id' => $this->newClassId,
            'initiated_by' => $this->initiatedByUserId,
            'notify_student' => $this->notifyStudent,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(StudentSectionTransferService $studentSectionTransferService, StudentTransferEmailService $studentTransferEmailService): void
    {
        try {
            Log::info('Starting student section transfer job', [
                'job_id' => $this->jobId,
                'class_enrollment_id' => $this->classEnrollmentId,
                'new_class_id' => $this->newClassId,
            ]);

            // Find the class enrollment record
            $classEnrollment = ClassEnrollment::with(['student', 'class'])
                ->find($this->classEnrollmentId);

            if (! $classEnrollment) {
                throw new Exception('Class enrollment not found: '.$this->classEnrollmentId);
            }

            // Perform the transfer
            $result = $studentSectionTransferService->transferStudent($classEnrollment, $this->newClassId);

            // Send email notifications
            $emailResults = $studentTransferEmailService->sendTransferNotifications($result, $this->notifyStudent);

            // Log email results
            Log::info('Email notifications processed for student transfer', [
                'job_id' => $this->jobId,
                'student_id' => $result['student_id'],
                'student_email_sent' => $emailResults['student_email_sent'],
                'faculty_email_sent' => $emailResults['faculty_email_sent'],
                'student_email_error' => $emailResults['student_email_error'],
                'faculty_email_error' => $emailResults['faculty_email_error'],
            ]);

            // Send success notification
            $this->sendSuccessNotification($result, $emailResults);

            Log::info('Student section transfer job completed successfully', [
                'job_id' => $this->jobId,
                'result' => $result,
                'email_results' => $emailResults,
            ]);

        } catch (Exception $exception) {
            Log::error('Student section transfer job failed', [
                'job_id' => $this->jobId,
                'class_enrollment_id' => $this->classEnrollmentId,
                'new_class_id' => $this->newClassId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Send failure notification
            $this->sendFailureNotification($exception);

            throw $exception;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $throwable): void
    {
        Log::error('MoveStudentToSectionJob permanently failed', [
            'job_id' => $this->jobId,
            'class_enrollment_id' => $this->classEnrollmentId,
            'new_class_id' => $this->newClassId,
            'attempts' => $this->attempts(),
            'exception' => $throwable->getMessage(),
        ]);

        $this->sendFailureNotification($throwable);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'student-transfer',
            'class-enrollment:'.$this->classEnrollmentId,
            'target-class:'.$this->newClassId,
            'job-id:'.$this->jobId,
        ];
    }

    /**
     * Send success notification to relevant users
     */
    private function sendSuccessNotification(array $result, array $emailResults = []): void
    {
        try {
            // Get users to notify (initiator and super admins)
            $usersToNotify = collect();

            if ($this->initiatedByUserId !== null && $this->initiatedByUserId !== 0) {
                $initiator = User::query()->find($this->initiatedByUserId);
                if ($initiator) {
                    $usersToNotify->push($initiator);
                }
            }

            // Add super admins
            $superAdmins = User::role('super_admin')->get();
            $usersToNotify = $usersToNotify->merge($superAdmins)->unique('id');

            // Find student enrollment for action link
            $studentEnrollment = null;
            if ($result['student_enrollment_id']) {
                $studentEnrollment = StudentEnrollment::query()->find($result['student_enrollment_id']);
            }

            foreach ($usersToNotify as $userToNotify) {
                $emailStatus = '';
                if ($emailResults !== []) {
                    $emailStatus = "\n**Email Notifications:**";
                    $emailStatus .= $emailResults['student_email_sent'] ? ' ✅ Student notified' : ' ❌ Student email failed';
                    $emailStatus .= $emailResults['faculty_email_sent'] ? ' | ✅ Faculty notified' : ' | ❌ Faculty email failed/not applicable';
                }

                $notification = Notification::make()
                    ->title('Student Successfully Moved Between Sections')
                    ->body("
                        **Student:** {$result['student_name']}
                        **Subject:** {$result['subject_code']}
                        **From:** Section {$result['old_section']}
                        **To:** Section {$result['new_section']}
                        **Updated Records:** Class Enrollment".($result['subject_enrollment_updated'] ? ' & Subject Enrollment' : '').$emailStatus
                    )
                    ->success()
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->duration(8000);

                // Add action to view student enrollment if available
                if ($studentEnrollment) {
                    $notification->actions([
                        Action::make('view_enrollment')
                            ->label('View Student Enrollment')
                            ->icon('heroicon-o-eye')
                            ->url(route('filament.admin.resources.student-enrollments.view', ['record' => $studentEnrollment->id]))
                            ->openUrlInNewTab(),
                    ]);
                } else {
                    $notification->actions([
                        Action::make('view_student')
                            ->label('View Student Profile')
                            ->icon('heroicon-o-user')
                            ->url(route('filament.admin.resources.students.view', ['record' => $result['student_id']]))
                            ->openUrlInNewTab(),
                    ]);
                }

                $notification->sendToDatabase($userToNotify);
            }

            Log::info('Success notifications sent for student transfer', [
                'job_id' => $this->jobId,
                'student_id' => $result['student_id'],
                'notification_count' => $usersToNotify->count(),
            ]);

        } catch (Exception $exception) {
            Log::error('Failed to send success notification for student transfer', [
                'job_id' => $this->jobId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Send failure notification to relevant users
     */
    private function sendFailureNotification(Throwable $throwable): void
    {
        try {
            // Get users to notify
            $usersToNotify = collect();

            if ($this->initiatedByUserId !== null && $this->initiatedByUserId !== 0) {
                $initiator = User::query()->find($this->initiatedByUserId);
                if ($initiator) {
                    $usersToNotify->push($initiator);
                }
            }

            // Add super admins
            $superAdmins = User::role('super_admin')->get();
            $usersToNotify = $usersToNotify->merge($superAdmins)->unique('id');

            // Get class enrollment for context
            $classEnrollment = ClassEnrollment::with(['student', 'class'])
                ->find($this->classEnrollmentId);

            $studentName = $classEnrollment?->student?->full_name ?? 'Student ID: '.$this->classEnrollmentId;
            $currentSection = $classEnrollment?->class?->section ?? 'Unknown';

            foreach ($usersToNotify as $userToNotify) {
                Notification::make()
                    ->title('Student Section Transfer Failed')
                    ->body("
                        **Student:** {$studentName}
                        **Current Section:** {$currentSection}
                        **Target Class ID:** {$this->newClassId}
                        **Error:** {$throwable->getMessage()}
                        **Job ID:** {$this->jobId}

                        Please check the system logs for more details or contact the system administrator.
                    ")
                    ->danger()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->duration(12000)
                    ->persistent()
                    ->sendToDatabase($userToNotify);
            }

            Log::info('Failure notifications sent for student transfer', [
                'job_id' => $this->jobId,
                'notification_count' => $usersToNotify->count(),
            ]);

        } catch (Exception $exception) {
            Log::error('Failed to send failure notification for student transfer', [
                'job_id' => $this->jobId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
