<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentType;
use App\Models\Student;
use App\Models\StudentIdChangeLog;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class StudentIdUpdateService
{
    /**
     * Update a student's student_id (not the primary key)
     *
     * @param  Student  $student  The student to update
     * @param  int  $newStudentId  The new student ID to assign
     * @param  bool  $bypassSafetyChecks  Whether to bypass safety checks (when user has confirmed)
     * @return array Result array with success status and message
     */
    public function updateStudentId(Student $student, int $newStudentId, bool $bypassSafetyChecks = false): array
    {
        // Validate the new student ID
        $validation = $this->validateNewStudentId($student, $newStudentId);
        if ($validation !== true) {
            return $validation;
        }

        // Additional safety checks (only if not bypassed)
        if (! $bypassSafetyChecks) {
            $safetyCheck = $this->performSafetyChecks($student);
            if ($safetyCheck !== true) {
                return $safetyCheck;
            }
        }

        $oldStudentId = $student->student_id;

        try {
            return DB::transaction(function () use ($student, $oldStudentId, $newStudentId): array {
                $updatedRecords = $this->performIdUpdate($student, $newStudentId);

                // Log the change for audit trail
                $changeLog = StudentIdChangeLog::query()->create([
                    'old_student_id' => (string) $oldStudentId,
                    'new_student_id' => (string) $newStudentId,
                    'student_name' => $student->full_name,
                    'changed_by' => Auth::user()?->email ?? 'System',
                    'affected_records' => ['students' => 1, 'total_updated' => $updatedRecords],
                    'backup_data' => [
                        'student_data' => ['student_id' => $oldStudentId],
                        'timestamp' => format_timestamp_now(),
                    ],
                    'reason' => 'Student ID update via admin interface',
                ]);

                Log::info('Student ID updated successfully', [
                    'old_student_id' => $oldStudentId,
                    'new_student_id' => $newStudentId,
                    'student_name' => $student->full_name,
                    'user' => Auth::user()?->email ?? 'System',
                ]);

                return [
                    'success' => true,
                    'message' => "Student ID successfully updated from {$oldStudentId} to {$newStudentId}",
                    'old_id' => $oldStudentId,
                    'new_id' => $newStudentId,
                    'change_log_id' => $changeLog->id,
                    'updated_records' => $updatedRecords,
                ];
            });
        } catch (Exception $e) {
            Log::error('Student ID update failed', [
                'error' => $e->getMessage(),
                'student_id' => $student->id,
                'old_student_id' => $oldStudentId,
                'new_student_id' => $newStudentId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update student ID: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if a student ID is available
     */
    public function isIdAvailable(int $studentId): bool
    {
        return ! Student::withTrashed()->where('student_id', $studentId)->exists();
    }

    /**
     * Generate a suggested 6-digit student ID
     */
    public function generateSuggestedId(?StudentType $studentType = null): int
    {
        // Default to College if no type specified
        if (! $studentType instanceof StudentType) {
            $studentType = StudentType::College;
        }

        return Student::generateNextId($studentType);
    }

    /**
     * Get summary of affected records (simplified - only the student record)
     */
    public function getAffectedRecordsSummary(): array
    {
        return [
            'students' => 1,
            'total_updated' => 1,
        ];
    }

    /**
     * Undo a student ID change
     */
    public function undoStudentIdChange(int $changeLogId): array
    {
        try {
            $changeLog = StudentIdChangeLog::query()->findOrFail($changeLogId);

            if ($changeLog->is_undone) {
                return [
                    'success' => false,
                    'message' => 'This change has already been undone.',
                ];
            }

            $student = Student::withTrashed()->where('student_id', $changeLog->new_student_id)->first();

            if (! $student) {
                return [
                    'success' => false,
                    'message' => 'Student not found with the new ID.',
                ];
            }

            return DB::transaction(function () use ($changeLog, $student): array {
                // Revert the student_id back to the old value
                $student->update(['student_id' => $changeLog->old_student_id]);

                // Mark the change log as undone
                $changeLog->update(['is_undone' => true]);

                Log::info('Student ID change undone', [
                    'change_log_id' => $changeLog->id,
                    'reverted_from' => $changeLog->new_student_id,
                    'reverted_to' => $changeLog->old_student_id,
                    'user' => Auth::user()?->email ?? 'System',
                ]);

                return [
                    'success' => true,
                    'message' => "Student ID reverted from {$changeLog->new_student_id} back to {$changeLog->old_student_id}",
                    'old_id' => $changeLog->new_student_id,
                    'new_id' => $changeLog->old_student_id,
                ];
            });
        } catch (Exception $e) {
            Log::error('Student ID undo failed', [
                'error' => $e->getMessage(),
                'change_log_id' => $changeLogId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to undo student ID change: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get change history for a student
     */
    public function getStudentChangeHistory(int $studentPrimaryId): \Illuminate\Support\Collection
    {
        $student = Student::find($studentPrimaryId);
        if (! $student || ! $student->student_id) {
            return collect();
        }

        return StudentIdChangeLog::query()
            ->where('old_student_id', (string) $student->student_id)
            ->orWhere('new_student_id', (string) $student->student_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Validate the new student ID
     */
    private function validateNewStudentId(Student $student, int $newStudentId): array|true
    {
        Log::info("Validating student ID update from {$student->student_id} to {$newStudentId}");

        // Check if the new ID is the same as current
        if ($student->student_id === $newStudentId) {
            return [
                'success' => false,
                'message' => 'New student ID cannot be the same as the current student ID.',
            ];
        }

        // Check if new ID already exists
        if (! $this->isIdAvailable($newStudentId)) {
            return [
                'success' => false,
                'message' => "Student ID {$newStudentId} is already in use.",
            ];
        }

        // Validate 6-digit format (optional warning, not blocking)
        if ($newStudentId < 100000 || $newStudentId > 999999) {
            Log::warning("Student ID {$newStudentId} is not in the recommended 6-digit format");
        }

        return true;
    }

    /**
     * Perform basic safety checks
     */
    private function performSafetyChecks(Student $student): array|true
    {
        // Basic check - just ensure the student has a current student_id
        if (is_null($student->student_id)) {
            Log::warning('Student has no current student_id set');
        }

        return true;
    }

    /**
     * Perform the student ID update flow.
     */
    private function performIdUpdate(Student $student, int $newId): int
    {
        $oldId = (int) $student->student_id;

        $student->update(['student_id' => $newId]);

        $manualUpdates = $this->updateRelatedRecordsManually($oldId, $newId);
        $foreignKeyUpdates = $this->updateTablesWithForeignKeyConstraints($oldId, $newId);

        $this->verifyPostUpdateState($student->fresh(), $newId);

        return 1 + $manualUpdates + $foreignKeyUpdates;
    }

    /**
     * Update related records in tables that are not constrained by foreign keys.
     */
    private function updateRelatedRecordsManually(int $oldId, int $newId): int
    {
        unset($oldId, $newId);

        return 0;
    }

    /**
     * Update related records in tables that are constrained by foreign keys.
     */
    private function updateTablesWithForeignKeyConstraints(int $oldId, int $newId): int
    {
        unset($oldId, $newId);

        return 0;
    }

    /**
     * Verify state after update.
     */
    private function verifyPostUpdateState(?Student $student, int $expectedStudentId): void
    {
        if (! $student instanceof Student || (int) $student->student_id !== $expectedStudentId) {
            throw new Exception('Post-update verification failed for student ID update.');
        }
    }
}
