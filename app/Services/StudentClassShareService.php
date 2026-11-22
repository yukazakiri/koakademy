<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\StrandSubject;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

final class StudentClassShareService
{
    /**
     * Get student classes for sidebar collapsible menu.
     *
     * @return array<int, array{id: int, subject_code: string, subject_title: string, section: string, classification: string, students_count: int, accent_color: string}>
     */
    public function getStudentClasses(?User $user): array
    {
        if (! $user || ! $user->isStudentRole()) {
            return [];
        }

        $student = Student::query()->where('user_id', $user->id)->first()
            ?? Student::query()->where('email', $user->email)->first();

        if (! $student) {
            return [];
        }

        $enrollments = ClassEnrollment::query()
            ->where('student_id', $student->id)
            ->currentAcademicPeriod()
            ->with([
                'class' => static fn ($query) => $query
                    ->select(['id', 'subject_code', 'section', 'classification', 'subject_id', 'shs_strand_id', 'settings'])
                    ->withCount('class_enrollments'),
            ])
            ->get();

        if ($enrollments->isEmpty()) {
            return [];
        }

        $classes = $enrollments->pluck('class')->filter()->values();
        if ($classes->isEmpty()) {
            return [];
        }

        [$subjectsById, $subjectsByCode, $shsSubjectsByCode] = $this->loadSubjectsForClasses($classes);

        return $classes->map(function ($class) use ($subjectsById, $subjectsByCode, $shsSubjectsByCode): array {
            $subject = ($class->subject_id ? $subjectsById->get($class->subject_id) : null)
                ?? ($class->classification === 'shs' && $class->subject_code ? $shsSubjectsByCode->get($class->subject_code) : null)
                ?? ($class->subject_code ? $subjectsByCode->get($class->subject_code) : null)
                ?? ($class->subject_code ? $shsSubjectsByCode->get($class->subject_code) : null);

            $settings = $class->settings ?? [];
            $accentColor = $settings['accent_color'] ?? '#3b82f6';

            return [
                'id' => $class->id,
                'subject_code' => $subject?->code ?? $class->subject_code ?? 'N/A',
                'subject_title' => $subject?->title ?? 'N/A',
                'section' => $class->section ?? 'N/A',
                'classification' => $class->classification ?? 'college',
                'students_count' => $class->class_enrollments_count ?? 0,
                'accent_color' => $accentColor,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, mixed>  $classes
     * @return array{0: Collection<int, Subject>, 1: Collection<string, Subject>, 2: Collection<string, StrandSubject>}
     */
    private function loadSubjectsForClasses(Collection $classes): array
    {
        $subjectIds = $classes->pluck('subject_id')->filter()->unique()->values();
        $subjectCodes = $classes->pluck('subject_code')->filter()->unique()->values();

        $subjects = collect();
        if ($subjectIds->isNotEmpty() || $subjectCodes->isNotEmpty()) {
            $subjects = Subject::query()
                ->select(['id', 'code', 'title'])
                ->where(function ($query) use ($subjectIds, $subjectCodes): void {
                    if ($subjectIds->isNotEmpty()) {
                        $query->whereIn('id', $subjectIds);
                    }
                    if ($subjectCodes->isNotEmpty()) {
                        $subjectIds->isNotEmpty()
                            ? $query->orWhereIn('code', $subjectCodes)
                            : $query->whereIn('code', $subjectCodes);
                    }
                })
                ->get();
        }

        $shsCodes = $classes->where('classification', 'shs')->pluck('subject_code')->filter()->unique()->values();
        $shsSubjects = $shsCodes->isNotEmpty()
            ? StrandSubject::query()->select(['id', 'code', 'title'])->whereIn('code', $shsCodes)->get()->keyBy('code')
            : collect();

        return [
            $subjects->keyBy('id'),
            $subjects->keyBy('code'),
            $shsSubjects,
        ];
    }
}
