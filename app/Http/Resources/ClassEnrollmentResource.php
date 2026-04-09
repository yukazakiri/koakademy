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
        return [
            'id' => $this->id,
            'class_id' => $this->class_id,
            'student_id' => $this->student_id,
            'completion_date' => $this->completion_date,
            'status' => $this->status,
            'remarks' => $this->remarks,

            // Grades
            'prelim_grade' => $this->prelim_grade,
            'midterm_grade' => $this->midterm_grade,
            'finals_grade' => $this->finals_grade,
            'total_average' => $this->total_average,
            'is_grades_finalized' => $this->is_grades_finalized,
            'is_grades_verified' => $this->is_grades_verified,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at,
            'verification_notes' => $this->verification_notes,

            // Computed Grade Information
            'letter_grade' => $this->when(
                $this->total_average !== null,
                fn (): ?string => $this->getLetterGrade()
            ),
            'grade_point' => $this->when(
                $this->total_average !== null,
                fn (): ?float => $this->getGradePoint()
            ),
            'is_passing' => $this->when(
                $this->total_average !== null,
                fn (): bool => $this->total_average >= 75
            ),
            'has_all_grades' => $this->when(
                $this->prelim_grade !== null || $this->midterm_grade !== null || $this->finals_grade !== null,
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
                $this->verified_by,
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
                $this->verified_at,
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
