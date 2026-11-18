<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ClassEnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isStudent = $request->user()?->isStudentRole() === true;
        $published = null;
        if ($isStudent && $this->student) {
            $service = app(\App\Services\GradeReleaseService::class);
            $published = $service->publishedClass($this->resource, $service->releases($this->student), $this->student);
        }

        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'student_id' => $this->student_id,
            'completion_date' => $this->completion_date,
            'status' => $this->status,
            'remarks' => $isStudent ? null : $this->remarks,

            // Grades
            'prelim_grade' => $this->prelim_grade,
            'midterm_grade' => $this->midterm_grade,
            'finals_grade' => $isStudent ? ($published['finals_grade'] ?? null) : $this->finals_grade,
            'total_average' => $isStudent ? ($published['total_average'] ?? null) : $this->total_average,
            'is_grades_finalized' => $isStudent ? null : $this->is_grades_finalized,
            'is_grades_verified' => $isStudent ? null : $this->is_grades_verified,
            'verified_by' => $isStudent ? null : $this->verified_by,
            'verified_at' => $isStudent ? null : $this->verified_at,
            'verification_notes' => $isStudent ? null : $this->verification_notes,

            // Evaluated from the immutable policy version assigned when grades are saved.
            'grade_symbol' => $isStudent ? ($published['grade_symbol'] ?? null) : $this->grade_symbol,
            'grade_outcome' => $isStudent ? ($published['grade_outcome'] ?? null) : $this->grade_outcome,
            'grade_quality_points' => $isStudent ? ($published['grade_quality_points'] ?? null) : $this->grade_quality_points,
            'grading_components' => $isStudent ? ($published['grading_components'] ?? null) : $this->grading_components,
            'grading_policy_version_id' => $isStudent ? null : $this->grading_policy_version_id,
            'letter_grade' => $isStudent ? ($published['grade_symbol'] ?? null) : $this->grade_symbol,
            'grade_point' => $isStudent ? ($published['grade_quality_points'] ?? null) : $this->grade_quality_points,
            'is_passing' => $isStudent ? (isset($published['grade_outcome']) ? $published['grade_outcome'] === 'pass' : null) : $this->when($this->grade_outcome !== null, fn (): bool => $this->grade_outcome === 'pass'),
            'has_all_grades' => $this->when(
                ! $isStudent && ($this->prelim_grade !== null || $this->midterm_grade !== null || $this->finals_grade !== null),
                fn (): bool => $this->prelim_grade !== null && $this->midterm_grade !== null && $this->finals_grade !== null
            ),

            // Relationships
            'class' => $this->when(
                $this->relationLoaded('class'),
                fn (): array => [
                    'id' => $this->class?->id,
                    'section' => $this->class?->section,
                    'subject_code' => $this->class?->subject_code,
                    'subject_title' => $this->class?->subject?->title ?? $this->class?->shsSubject?->title,
                    'faculty' => $this->class?->faculty?->full_name ?? $this->class?->faculty?->first_name.' '.$this->class?->faculty?->last_name,
                ]
            ),
            'student' => $this->when(
                $this->relationLoaded('student'),
                fn (): array => [
                    'id' => $this->student?->id,
                    'student_id' => $this->student?->student_id,
                    'full_name' => $this->student?->full_name,
                    'first_name' => $this->student?->first_name,
                    'last_name' => $this->student?->last_name,
                    'email' => $this->student?->email,
                ]
            ),
            'verifier' => $this->when(
                ! $isStudent && $this->verified_by,
                fn (): array => [
                    'id' => $this->verifier?->id,
                    'name' => $this->verifier?->name,
                    'email' => $this->verifier?->email,
                ]
            ),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Conditional fields
            'created_at_formatted' => $this->when(
                $this->created_at,
                fn () => $this->created_at->format('Y-m-d H:i:s')
            ),
            'updated_at_formatted' => $this->when(
                $this->updated_at,
                fn () => $this->updated_at->format('Y-m-d H:i:s')
            ),
            'completion_date_formatted' => $this->when(
                $this->completion_date,
                fn () => $this->completion_date->format('Y-m-d')
            ),
            'verified_at_formatted' => $this->when(
                ! $isStudent && $this->verified_at,
                fn () => $this->verified_at->format('Y-m-d H:i:s')
            ),
        ];
    }

    /**
     * Get letter grade based on numerical grade
     */
    private function getLetterGrade(): ?string
    {
        if ($this->total_average === null) {
            return null;
        }

        return match (true) {
            $this->total_average >= 97 => 'A+',
            $this->total_average >= 93 => 'A',
            $this->total_average >= 90 => 'A-',
            $this->total_average >= 87 => 'B+',
            $this->total_average >= 83 => 'B',
            $this->total_average >= 80 => 'B-',
            $this->total_average >= 77 => 'C+',
            $this->total_average >= 73 => 'C',
            $this->total_average >= 70 => 'C-',
            $this->total_average >= 67 => 'D+',
            $this->total_average >= 63 => 'D',
            $this->total_average >= 60 => 'D-',
            default => 'F',
        };
    }

    /**
     * Get grade point based on numerical grade
     */
    private function getGradePoint(): ?float
    {
        if ($this->total_average === null) {
            return null;
        }

        return match (true) {
            $this->total_average >= 97 => 4.0,
            $this->total_average >= 93 => 3.7,
            $this->total_average >= 90 => 3.3,
            $this->total_average >= 87 => 3.0,
            $this->total_average >= 83 => 2.7,
            $this->total_average >= 80 => 2.3,
            $this->total_average >= 77 => 2.0,
            $this->total_average >= 73 => 1.7,
            $this->total_average >= 70 => 1.3,
            $this->total_average >= 67 => 1.0,
            $this->total_average >= 63 => 0.7,
            $this->total_average >= 60 => 0.3,
            default => 0.0,
        };
    }
}
