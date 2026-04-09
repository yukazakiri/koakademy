<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\FacultySectionTransferNotification;
use App\Mail\StudentSectionTransferNotification;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service class for handling email notifications related to student section transfers
 * Provides consolidated email functionality to prevent inbox spam
 */
final readonly class StudentTransferEmailService
{
    public function __construct(
        private GeneralSettingsService $generalSettingsService
    ) {}

    /**
     * Send email notifications for a student section transfer
     *
     * @param  array  $transferResult  The result from StudentSectionTransferService::transferStudent
     * @param  bool  $notifyStudent  Whether to send email notification to the student
     * @return array Results of email sending attempts
     */
    public function sendTransferNotifications(array $transferResult, bool $notifyStudent = true): array
    {
        $results = [
            'student_email_sent' => false,
            'faculty_email_sent' => false,
            'student_email_error' => null,
            'faculty_email_error' => null,
        ];

        // Get the student and classes
        $student = Student::query()->find($transferResult['student_id']);
        $oldClass = Classes::with('Faculty')->find($transferResult['old_class_id']);
        $newClass = Classes::with('Faculty')->find($transferResult['new_class_id']);

        if (! $student || ! $oldClass || ! $newClass) {
            Log::error('Missing data for transfer email notifications', [
                'student_found' => $student !== null,
                'old_class_found' => $oldClass !== null,
                'new_class_found' => $newClass !== null,
                'transfer_result' => $transferResult,
            ]);

            return $results;
        }

        // Send student notification only if requested
        if ($notifyStudent) {
            $results['student_email_sent'] = $this->sendStudentNotification($student, $transferResult);
        } else {
            Log::info('Student email notification skipped by request', [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
            ]);
        }

        // Send faculty notification if faculty is assigned to the new class
        if ($newClass->Faculty) {
            $results['faculty_email_sent'] = $this->sendFacultyNotification($newClass->Faculty, $student, $newClass, $transferResult);
        }

        return $results;
    }

    /**
     * Send consolidated email notifications for bulk student transfers
     *
     * @param  array  $bulkTransferResults  Results from StudentSectionTransferService::transferMultipleStudents
     * @param  int  $targetClassId  The target class ID for all transfers
     * @param  bool  $notifyStudents  Whether to send email notifications to students
     * @return array Results of email sending attempts
     */
    public function sendBulkTransferNotifications(array $bulkTransferResults, int $targetClassId, bool $notifyStudents = true): array
    {
        $results = [
            'student_emails_sent' => 0,
            'faculty_email_sent' => false,
            'student_email_errors' => [],
            'faculty_email_error' => null,
        ];

        // Get the target class
        $targetClass = Classes::with('Faculty')->find($targetClassId);
        if (! $targetClass) {
            Log::error('Target class not found for bulk transfer notifications', ['class_id' => $targetClassId]);

            return $results;
        }

        // Send individual student notifications only if requested
        if ($notifyStudents) {
            foreach ($bulkTransferResults['successful_transfers'] as $transferResult) {
                $student = Student::query()->find($transferResult['student_id']);
                $oldClass = Classes::query()->find($transferResult['old_class_id']);

                if ($student && $oldClass) {
                    if ($this->sendStudentNotification($student, $transferResult)) {
                        $results['student_emails_sent']++;
                    } else {
                        $results['student_email_errors'][] = [
                            'student_id' => $student->id,
                            'student_name' => $student->full_name,
                            'error' => 'Failed to send email',
                        ];
                    }
                }
            }
        } else {
            Log::info('Student email notifications skipped for bulk transfer by request', [
                'target_class_id' => $targetClassId,
                'student_count' => count($bulkTransferResults['successful_transfers']),
            ]);
        }

        // Send consolidated faculty notification if faculty is assigned
        if ($targetClass->Faculty && ! empty($bulkTransferResults['successful_transfers'])) {
            $results['faculty_email_sent'] = $this->sendBulkFacultyNotification(
                $targetClass->Faculty,
                $bulkTransferResults['successful_transfers'],
                $targetClass
            );
        }

        return $results;
    }

    /**
     * Send email notification to the student about their section transfer
     *
     * @param  Student  $student  The student being transferred
     * @param  array  $transferResult  The transfer result data
     * @return bool Whether the email was sent successfully
     */
    private function sendStudentNotification(Student $student, array $transferResult): bool
    {
        try {
            // Check if student has an email address
            if (empty($student->email)) {
                Log::warning('Student has no email address for transfer notification', [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                ]);

                return false;
            }

            // Get student portal URL
            $portalUrl = $this->generalSettingsService->getStudentPortalUrl();

            // Prepare email data
            $emailData = [
                'student_name' => $student->full_name,
                'subject_code' => $transferResult['subject_code'],
                'old_section' => $transferResult['old_section'],
                'new_section' => $transferResult['new_section'],
                'transfer_date' => now()->format('F j, Y'),
                'portal_url' => $portalUrl,
                'school_year' => $this->generalSettingsService->getCurrentSchoolYearString(),
                'semester' => $this->generalSettingsService->getAvailableSemesters()[$this->generalSettingsService->getCurrentSemester()] ?? '',
            ];

            // Send the email
            Mail::to($student->email)->send(new StudentSectionTransferNotification($emailData));

            Log::info('Student transfer notification email sent successfully', [
                'student_id' => $student->id,
                'student_email' => $student->email,
                'subject_code' => $transferResult['subject_code'],
                'old_section' => $transferResult['old_section'],
                'new_section' => $transferResult['new_section'],
            ]);

            return true;

        } catch (Exception $exception) {
            Log::error('Failed to send student transfer notification email', [
                'student_id' => $student->id,
                'student_email' => $student->email ?? 'N/A',
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send email notification to faculty about a student transfer to their class
     *
     * @param  Faculty  $faculty  The faculty member assigned to the destination class
     * @param  Student  $student  The student being transferred
     * @param  Classes  $classes  The class they're being moved to
     * @param  array  $transferResult  The transfer result data
     * @return bool Whether the email was sent successfully
     */
    private function sendFacultyNotification(Faculty $faculty, Student $student, Classes $classes, array $transferResult): bool
    {
        try {
            // Check if faculty has an email address
            if (empty($faculty->email)) {
                Log::warning('Faculty has no email address for transfer notification', [
                    'faculty_id' => $faculty->id,
                    'faculty_name' => $faculty->full_name,
                ]);

                return false;
            }

            // Prepare email data
            $emailData = [
                'faculty_name' => $faculty->full_name,
                'student_name' => $student->full_name,
                'student_id' => $student->id,
                'student_course' => $student->course?->name ?? 'N/A',
                'subject_code' => $transferResult['subject_code'],
                'old_section' => $transferResult['old_section'],
                'new_section' => $transferResult['new_section'],
                'transfer_date' => now()->format('F j, Y'),
                'school_year' => $this->generalSettingsService->getCurrentSchoolYearString(),
                'semester' => $this->generalSettingsService->getAvailableSemesters()[$this->generalSettingsService->getCurrentSemester()] ?? '',
                'class_id' => $classes->id,
            ];

            // Send the email
            Mail::to($faculty->email)->send(new FacultySectionTransferNotification($emailData));

            Log::info('Faculty transfer notification email sent successfully', [
                'faculty_id' => $faculty->id,
                'faculty_email' => $faculty->email,
                'student_id' => $student->id,
                'subject_code' => $transferResult['subject_code'],
                'new_section' => $transferResult['new_section'],
            ]);

            return true;

        } catch (Exception $exception) {
            Log::error('Failed to send faculty transfer notification email', [
                'faculty_id' => $faculty->id,
                'faculty_email' => $faculty->email ?? 'N/A',
                'student_id' => $student->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send consolidated faculty notification for bulk transfers
     *
     * @param  Faculty  $faculty  The faculty member
     * @param  array  $successfulTransfers  Array of successful transfer results
     * @param  Classes  $classes  The target class
     * @return bool Whether the email was sent successfully
     */
    private function sendBulkFacultyNotification(Faculty $faculty, array $successfulTransfers, Classes $classes): bool
    {
        try {
            if (empty($faculty->email)) {
                Log::warning('Faculty has no email address for bulk transfer notification', [
                    'faculty_id' => $faculty->id,
                    'faculty_name' => $faculty->full_name,
                ]);

                return false;
            }

            // Prepare consolidated student data
            $students = [];
            foreach ($successfulTransfers as $successfulTransfer) {
                $student = Student::query()->find($successfulTransfer['student_id']);
                if ($student) {
                    $students[] = [
                        'name' => $student->full_name,
                        'id' => $student->id,
                        'course' => $student->course?->name ?? 'N/A',
                        'old_section' => $successfulTransfer['old_section'],
                    ];
                }
            }

            $emailData = [
                'faculty_name' => $faculty->full_name,
                'students' => $students,
                'student_count' => count($students),
                'subject_code' => $successfulTransfers[0]['subject_code'] ?? 'N/A',
                'new_section' => $successfulTransfers[0]['new_section'] ?? 'N/A',
                'transfer_date' => now()->format('F j, Y'),
                'school_year' => $this->generalSettingsService->getCurrentSchoolYearString(),
                'semester' => $this->generalSettingsService->getAvailableSemesters()[$this->generalSettingsService->getCurrentSemester()] ?? '',
                'class_id' => $classes->id,
            ];

            // Send the email
            Mail::to($faculty->email)->send(new FacultySectionTransferNotification($emailData, true));

            Log::info('Bulk faculty transfer notification email sent successfully', [
                'faculty_id' => $faculty->id,
                'faculty_email' => $faculty->email,
                'student_count' => count($students),
                'target_class_id' => $classes->id,
            ]);

            return true;

        } catch (Exception $exception) {
            Log::error('Failed to send bulk faculty transfer notification email', [
                'faculty_id' => $faculty->id,
                'faculty_email' => $faculty->email ?? 'N/A',
                'target_class_id' => $classes->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }
}
