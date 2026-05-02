<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Student;
use App\Models\User;

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

        $student = Student::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();

        if (! $student) {
            return [];
        }

        $enrollments = ClassEnrollment::where('student_id', $student->id)
            ->currentAcademicPeriod()
            ->with(['class' => fn ($query) => $query->withCount('class_enrollments')])
            ->get();

        if ($enrollments->isEmpty()) {
            return [];
        }

        return $enrollments->map(function (ClassEnrollment $enrollment): ?array {
            $class = $enrollment->class;

            if (! $class) {
                return null;
            }

            $subject = $class->subject
                ?? $class->subjectByCode
                ?? $class->subjectByCodeFallback
                ?? $class->shsSubject;

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
        })->filter()->values()->all();
    }
}
