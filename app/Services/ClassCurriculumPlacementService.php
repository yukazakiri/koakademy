<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Classes;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ClassCurriculumPlacementService
{
    /**
     * @return Collection<int, Subject>
     */
    public function subjectsForClass(Classes $class): Collection
    {
        $class->loadMissing(['subjects', 'Subject']);

        /** @var Collection<int, Subject> $subjects */
        $subjects = $class->subjects
            ->filter(fn (mixed $subject): bool => $subject instanceof Subject)
            ->keyBy(fn (Subject $subject): int => (int) $subject->getKey());

        if ($class->Subject instanceof Subject) {
            $subjects->put((int) $class->Subject->getKey(), $class->Subject);
        }

        return $subjects->values();
    }

    /**
     * @return Collection<int, Subject>
     */
    public function subjectsForCourse(Classes $class, int $courseId): Collection
    {
        return $this->subjectsForClass($class)
            ->filter(fn (Subject $subject): bool => (int) $subject->course_id === $courseId)
            ->values();
    }

    /**
     * @return list<int>
     */
    public function yearsForClass(Classes $class): array
    {
        return $this->curriculumYears($this->subjectsForClass($class));
    }

    /**
     * @return list<int>
     */
    public function yearsForCourse(Classes $class, int $courseId): array
    {
        $years = $this->curriculumYears($this->subjectsForCourse($class, $courseId));

        if ($years !== []) {
            return $years;
        }

        $fallback = (int) $class->academic_year;

        return $fallback > 0 ? [$fallback] : [];
    }

    /**
     * Validate college curriculum selections and apply the authoritative unanimous year.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizeCollegeAttributes(array $attributes, ?Classes $existingClass = null): array
    {
        $courseIds = $this->integerIds(
            $attributes['course_codes'] ?? $existingClass?->course_codes ?? []
        );
        $subjectIds = $this->integerIds($attributes['subject_ids'] ?? $existingClass?->subject_ids ?? []);

        if ($subjectIds === []) {
            $subjectIds = $this->integerIds([
                $attributes['subject_id'] ?? $existingClass?->subject_id,
            ]);
        }

        $missingSelectionErrors = [];

        if ($courseIds === []) {
            $missingSelectionErrors['course_codes'] = 'Select at least one program for a college class.';
        }

        if ($subjectIds === []) {
            $missingSelectionErrors['subject_ids'] = 'Select at least one subject for a college class.';
        }

        if ($missingSelectionErrors !== []) {
            throw ValidationException::withMessages($missingSelectionErrors);
        }

        $availableCourseIds = Course::query()
            ->whereKey($courseIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (array_diff($courseIds, $availableCourseIds) !== []) {
            throw ValidationException::withMessages([
                'course_codes' => 'One or more selected programs are not available for this school.',
            ]);
        }

        /** @var Collection<int, Subject> $subjects */
        $subjects = Subject::query()
            ->whereKey($subjectIds)
            ->get(['id', 'course_id', 'academic_year']);

        if ($subjects->count() !== count($subjectIds)) {
            throw ValidationException::withMessages([
                'subject_ids' => 'One or more selected subjects no longer exist.',
            ]);
        }

        if ($subjects->contains(fn (Subject $subject): bool => ! in_array((int) $subject->course_id, $courseIds, true))) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Every selected subject must belong to one of the selected programs.',
            ]);
        }

        $years = $this->curriculumYears($subjects);
        $allSubjectsHaveYears = count($years) > 0
            && $subjects->every(fn (Subject $subject): bool => $this->validYear($subject->academic_year) !== null);

        if (! $allSubjectsHaveYears && $this->subjectSelectionChanged($subjectIds, $existingClass)) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Every newly selected subject must have a curriculum year before it can be assigned to a class.',
            ]);
        }

        if ($allSubjectsHaveYears && count($years) === 1) {
            $attributes['academic_year'] = $years[0];

            return $attributes;
        }

        if (count($years) > 1) {
            $fallbackYear = (int) ($attributes['academic_year'] ?? $existingClass?->academic_year ?? 0);

            if (! in_array($fallbackYear, $years, true)) {
                throw ValidationException::withMessages([
                    'academic_year' => sprintf(
                        'This shared class spans curriculum years %s. Choose one of those years as its fallback.',
                        implode(', ', $years),
                    ),
                ]);
            }
        }

        return $attributes;
    }

    public function yearLabel(int $year): string
    {
        return match ($year) {
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            default => $year > 0 ? "{$year}th Year" : 'N/A',
        };
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @return list<int>
     */
    private function curriculumYears(Collection $subjects): array
    {
        return $subjects
            ->map(fn (Subject $subject): ?int => $this->validYear($subject->academic_year))
            ->filter(fn (?int $year): bool => $year !== null)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function validYear(mixed $year): ?int
    {
        $value = (int) $year;

        return $value >= 1 && $value <= 4 ? $value : null;
    }

    /**
     * @return list<int>
     */
    private function integerIds(mixed $ids): array
    {
        return collect(is_array($ids) ? $ids : [])
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $subjectIds
     */
    private function subjectSelectionChanged(array $subjectIds, ?Classes $existingClass): bool
    {
        if (! $existingClass instanceof Classes) {
            return true;
        }

        $existingSubjectIds = $this->integerIds($existingClass->subject_ids ?? []);

        if ($existingSubjectIds === []) {
            $existingSubjectIds = $this->integerIds([$existingClass->subject_id]);
        }

        sort($subjectIds);
        sort($existingSubjectIds);

        return $subjectIds !== $existingSubjectIds;
    }
}
