<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Classes;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ClassAssignmentService
{
    public function getUnassignedClassOptions(): array
    {
        return Classes::currentAcademicPeriod()
            ->whereNull('faculty_id')
            ->get()
            ->mapWithKeys(fn (Classes $class): array => [$class->id => $this->formatClassLabel($class)])
            ->toArray();
    }

    public function formatClassLabel(Classes $classes): string
    {
        $type = $classes->isShs() ? 'SHS' : 'College';
        $info = $classes->isShs() ? $classes->formatted_track_strand : $classes->formatted_course_codes;

        $label = sprintf('[%s] %s - %s (%s)', $classes->subject_code, $classes->subject_title, $classes->section, $type);

        if ($info && $info !== 'N/A') {
            $label .= ' - '.$info;
        }

        return $label;
    }

    public function assignClassesToFaculty(array $classIds, string $facultyId): int
    {
        try {
            if ($classIds === []) {
                Log::info('No classes provided for assignment');

                return 0;
            }

            Classes::query()->whereIn('id', $classIds)
                ->update(['faculty_id' => $facultyId]);

            Log::info('Classes assigned to faculty successfully', [
                'faculty_id' => $facultyId,
                'class_count' => count($classIds),
                'class_ids' => $classIds,
            ]);

            return count($classIds);
        } catch (Exception $exception) {
            Log::error('Failed to assign classes to faculty', [
                'faculty_id' => $facultyId,
                'class_ids' => $classIds,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function distributeClassesAmongFaculty(array $classIds, Collection $facultyMembers): int
    {
        try {
            if ($classIds === [] || $facultyMembers->isEmpty()) {
                Log::info('No classes or faculty members provided for distribution', [
                    'class_count' => count($classIds),
                    'faculty_count' => $facultyMembers->count(),
                ]);

                return 0;
            }

            $facultyCount = $facultyMembers->count();

            foreach ($classIds as $index => $classId) {
                $facultyIndex = $index % $facultyCount;
                $facultyId = (string) $facultyMembers[$facultyIndex]['id'];

                Classes::query()->where('id', $classId)
                    ->update(['faculty_id' => $facultyId]);
            }

            Log::info('Classes distributed among faculty successfully', [
                'class_count' => count($classIds),
                'faculty_count' => $facultyCount,
                'class_ids' => $classIds,
            ]);

            return count($classIds);
        } catch (Exception $exception) {
            Log::error('Failed to distribute classes among faculty', [
                'class_ids' => $classIds,
                'faculty_count' => $facultyMembers->count(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function unassignClass(Classes $classes): void
    {
        try {
            $oldFacultyId = $classes->faculty_id;
            $classes->update(['faculty_id' => null]);

            Log::info('Class unassigned from faculty', [
                'class_id' => $classes->id,
                'subject_code' => $classes->subject_code,
                'section' => $classes->section,
                'old_faculty_id' => $oldFacultyId,
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to unassign class from faculty', [
                'class_id' => $classes->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function unassignClasses(Collection $classes): int
    {
        try {
            $count = $classes->count();
            $classIds = $classes->pluck('id')->toArray();

            $classes->each(fn ($record) => $record->update(['faculty_id' => null]));

            Log::info('Multiple classes unassigned from faculty', [
                'class_count' => $count,
                'class_ids' => $classIds,
            ]);

            return $count;
        } catch (Exception $exception) {
            Log::error('Failed to unassign multiple classes from faculty', [
                'class_count' => $classes->count(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
