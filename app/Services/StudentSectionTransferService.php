<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
use App\Models\SubjectEnrollment;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service class for handling student section transfers
 * Provides optimized and organized methods for moving students between class sections
 */
final readonly class StudentSectionTransferService
{
    public function __construct(
        private GeneralSettingsService $generalSettingsService
    ) {}

    /**
     * Transfer a single student to a different section
     *
     * @param  ClassEnrollment  $classEnrollment  The student's current class enrollment
     * @param  int  $newClassId  The target class ID
     * @param  bool  $forceOverload  Allow exceeding target class maximum_slots
     * @return array Transfer result with success status and details
     *
     * @throws Exception If transfer fails
     */
    public function transferStudent(ClassEnrollment $classEnrollment, int $newClassId, bool $forceOverload = false): array
    {
        $currentSchoolYear = $this->generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $this->generalSettingsService->getCurrentSemester();

        $oldClassId = (int) $classEnrollment->class_id;
        $studentId = (int) $classEnrollment->student_id;

        // Validate the transfer
        $validation = $this->validateTransfer($oldClassId, $newClassId, $studentId, $forceOverload);
        if (! $validation['valid']) {
            throw new Exception($validation['message']);
        }

        /** @var Classes $oldClass */
        $oldClass = $validation['old_class'];
        /** @var Classes $newClass */
        $newClass = $validation['new_class'];
        /** @var Student $student */
        $student = $validation['student'];

        $targetSchoolYear = filled($oldClass->school_year) ? (string) $oldClass->school_year : $currentSchoolYear;
        $targetSemester = $oldClass->semester !== null ? (int) $oldClass->semester : $currentSemester;

        try {
            DB::beginTransaction();

            // Update the class enrollment
            $classEnrollment->class_id = $newClassId;
            $classEnrollment->save();

            // Update the corresponding subject enrollment
            $subjectEnrollment = $this->updateSubjectEnrollment(
                $studentId,
                $oldClassId,
                $newClass,
                $targetSchoolYear,
                $targetSemester,
                (string) $oldClass->subject_code
            );

            DB::commit();

            $result = [
                'success' => true,
                'student_id' => $studentId,
                'student_name' => $student->full_name,
                'old_class_id' => $oldClassId,
                'new_class_id' => $newClassId,
                'old_section' => $oldClass->section,
                'new_section' => $newClass->section,
                'subject_code' => $oldClass->subject_code,
                'subject_enrollment_updated' => $subjectEnrollment instanceof SubjectEnrollment,
                'subject_enrollment_id' => $subjectEnrollment?->id,
                'student_enrollment_id' => $subjectEnrollment?->enrollment_id,
            ];

            $this->logTransferSuccess($result);

            return $result;

        } catch (Exception $exception) {
            DB::rollBack();

            $this->logTransferError($studentId, $oldClassId, $newClassId, $exception);

            throw new Exception('Failed to transfer student: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Transfer multiple students to a different section
     *
     * @param  Collection  $classEnrollments  Collection of ClassEnrollment models
     * @param  int  $newClassId  The target class ID
     * @param  bool  $forceOverload  Allow exceeding target class maximum_slots
     * @return array Bulk transfer results with success/error counts
     */
    public function transferMultipleStudents(Collection $classEnrollments, int $newClassId, bool $forceOverload = false): array
    {
        $results = [
            'total_students' => $classEnrollments->count(),
            'successful_transfers' => [],
            'failed_transfers' => [],
            'success_count' => 0,
            'error_count' => 0,
        ];

        foreach ($classEnrollments as $classEnrollment) {
            try {
                $transferResult = $this->transferStudent($classEnrollment, $newClassId, $forceOverload);
                $results['successful_transfers'][] = $transferResult;
                $results['success_count']++;
            } catch (Exception $e) {
                $results['failed_transfers'][] = [
                    'student_id' => $classEnrollment->student_id,
                    'student_name' => $classEnrollment->student?->full_name ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];
                $results['error_count']++;
            }
        }

        return $results;
    }

    /**
     * Get available target classes for a student transfer
     *
     * @param  int  $currentClassId  Current class ID
     * @return Collection Available classes for transfer
     */
    public function getAvailableTargetClasses(int $currentClassId): Collection
    {
        $currentClass = Classes::query()->find($currentClassId);
        if (! $currentClass) {
            return collect();
        }

        $currentSchoolYear = $this->generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $this->generalSettingsService->getCurrentSemester();

        return Classes::query()->where('subject_code', $currentClass->subject_code)
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->where('id', '!=', $currentClassId)
            ->with(['class_enrollments' => function ($query): void {
                $query->where('status', true)
                    ->select('class_id', DB::raw('count(*) as enrollment_count'))
                    ->groupBy('class_id');
            }])
            ->get()
            ->map(function ($class): Classes {
                $enrollmentCount = $class->class_enrollments->first()?->enrollment_count ?? 0;
                $class->current_enrollment = $enrollmentCount;
                $class->available_slots = $class->maximum_slots ? ($class->maximum_slots - $enrollmentCount) : null;
                $class->is_full = $class->maximum_slots && $enrollmentCount >= $class->maximum_slots;

                return $class;
            });
    }

    /**
     * Validate if a student transfer is possible
     *
     * @param  int  $oldClassId  Current class ID
     * @param  int  $newClassId  Target class ID
     * @param  int  $studentId  Student ID
     * @param  bool  $forceOverload  Allow exceeding target class maximum_slots
     * @return array Validation result with classes and student data
     */
    private function validateTransfer(int $oldClassId, int $newClassId, int $studentId, bool $forceOverload = false): array
    {
        // Get class information with optimized query
        $classes = Classes::query()->whereIn('id', [$oldClassId, $newClassId])
            ->get()
            ->keyBy('id');

        $oldClass = $classes->get($oldClassId);
        $newClass = $classes->get($newClassId);

        if (! $oldClass || ! $newClass) {
            return ['valid' => false, 'message' => 'One or both classes not found'];
        }

        // Validate that both classes are for the same subject
        if ($oldClass->subject_code !== $newClass->subject_code) {
            return ['valid' => false, 'message' => 'Cannot move student between different subjects'];
        }

        // Check if the new class has available slots (counting active enrollments only)
        if (! $forceOverload && $newClass->maximum_slots) {
            $currentEnrollmentCount = ClassEnrollment::query()
                ->where('class_id', $newClassId)
                ->where('status', true)
                ->count();
            if ($currentEnrollmentCount >= $newClass->maximum_slots) {
                return [
                    'valid' => false,
                    'message' => sprintf('Target class is full (Max: %s, Current: %s)', $newClass->maximum_slots, $currentEnrollmentCount),
                ];
            }
        }

        // Get student information
        $student = Student::query()->find($studentId);
        if (! $student) {
            return ['valid' => false, 'message' => 'Student not found'];
        }

        return [
            'valid' => true,
            'old_class' => $oldClass,
            'new_class' => $newClass,
            'student' => $student,
        ];
    }

    /**
     * Update the subject enrollment record for the transferred student
     *
     * @param  int  $studentId  Student ID
     * @param  int  $oldClassId  Old class ID
     * @param  Classes  $newClass  Target class model
     * @param  string  $schoolYear  Academic school year
     * @param  int  $semester  Academic semester
     * @param  string  $subjectCode  Subject code
     * @return SubjectEnrollment|null Updated subject enrollment or null if not found
     */
    private function updateSubjectEnrollment(
        int $studentId,
        int $oldClassId,
        Classes $newClass,
        string $schoolYear,
        int $semester,
        string $subjectCode
    ): ?SubjectEnrollment {
        $newClassId = (int) $newClass->id;
        $normalizedSchoolYear = GeneralSettingsService::normalizeSchoolYear($schoolYear);
        $schoolYearVariants = array_values(array_unique([
            $schoolYear,
            $normalizedSchoolYear,
            str_replace(' ', '', $normalizedSchoolYear),
        ]));

        $subjectEnrollment = SubjectEnrollment::query()
            ->where('student_id', $studentId)
            ->where('class_id', $oldClassId)
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $semester)
            ->whereHas('subject', function ($query) use ($subjectCode): void {
                $query->where('code', $subjectCode);
            })
            ->first();

        if ($subjectEnrollment) {
            $subjectEnrollment->class_id = $newClassId;
            $subjectEnrollment->section = $newClass->section ?? (string) $newClassId;
            $subjectEnrollment->save();

            Log::info('Subject enrollment updated during class move', [
                'student_id' => $studentId,
                'subject_enrollment_id' => $subjectEnrollment->id,
                'old_class_id' => $oldClassId,
                'new_class_id' => $newClassId,
                'new_section' => $subjectEnrollment->section,
                'subject_code' => $subjectCode,
            ]);

            return $subjectEnrollment;
        }

        Log::warning('No subject enrollment found to update during class move', [
            'student_id' => $studentId,
            'old_class_id' => $oldClassId,
            'new_class_id' => $newClassId,
            'subject_code' => $subjectCode,
            'school_year' => $schoolYear,
            'semester' => $semester,
        ]);

        return null;
    }

    /**
     * Log successful transfer
     */
    private function logTransferSuccess(array $result): void
    {
        Log::info('Student successfully moved between classes', [
            'student_id' => $result['student_id'],
            'student_name' => $result['student_name'],
            'old_class_id' => $result['old_class_id'],
            'old_section' => $result['old_section'],
            'new_class_id' => $result['new_class_id'],
            'new_section' => $result['new_section'],
            'subject_code' => $result['subject_code'],
            'subject_enrollment_updated' => $result['subject_enrollment_updated'],
        ]);
    }

    /**
     * Log transfer error
     */
    private function logTransferError(int $studentId, int $oldClassId, int $newClassId, Exception $exception): void
    {
        Log::error('Failed to move student between classes', [
            'student_id' => $studentId,
            'old_class_id' => $oldClassId,
            'new_class_id' => $newClassId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
