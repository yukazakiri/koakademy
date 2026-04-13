<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Api\Transformers;

use App\Models\Course;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Course $resource
 */
final class CourseTransformer extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'course_code' => $this->resource->course_code,
            'title' => $this->resource->title,
            'name' => $this->resource->name,

            // Course Information
            'course_information' => [
                'description' => $this->resource->description,
                'department' => $this->resource->department?->code,
                'curriculum_year' => $this->resource->curriculum_year,
                'remarks' => $this->resource->remarks,
                'is_active' => $this->resource->is_active,
            ],

            // Academic Structure
            'academic_structure' => [
                'units' => $this->resource->units,
                'lec_per_unit' => $this->resource->lec_per_unit,
                'lab_per_unit' => $this->resource->lab_per_unit,
                'year_level' => $this->resource->year_level,
                'semester' => $this->resource->semester,
                'school_year' => $this->resource->school_year,
            ],

            // Fees
            'fees' => [
                'miscellaneous' => $this->resource->miscellaneous ?? $this->resource->miscelaneous,
                'calculated_miscellaneous_fee' => $this->resource->getMiscellaneousFee(),
                'fee_based_on_curriculum' => $this->resource->getMiscellaneousFeeBasedOnCurriculumYear(),
            ],

            // Department Information
            'department_information' => $this->when(
                $this->resource->relationLoaded('department') && $this->resource->getRelation('department') instanceof \App\Models\Department,
                function (): array {
                    $dept = $this->resource->getRelation('department');

                    return [
                        'code' => $dept->code,
                        'name' => $dept->name,
                        'description' => $dept->description,
                    ];
                }
            ),

            // Statistics
            'statistics' => [
                'total_subjects' => $this->resource->subjects_count ?? 0,
                'total_students' => $this->resource->students_count ?? 0,
            ],

            // Subjects
            'subjects' => $this->when($this->resource->subjects, fn () => $this->resource->subjects->map(fn ($subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'description' => $subject->description,
                'units' => $subject->units,
                'lecture_hours' => $subject->lecture_hours,
                'laboratory_hours' => $subject->laboratory_hours,
                'academic_year' => $subject->academic_year,
                'semester' => $subject->semester,
                'formatted_academic_year' => $this->formatAcademicYear($subject->academic_year),
                'formatted_semester' => $this->formatSemester($subject->semester),
                'is_active' => $subject->is_active,
            ])),

            // Students Summary (limited data for performance)
            'students_summary' => $this->when($this->resource->students, fn (): array => [
                'total' => $this->resource->students->count(),
                'by_year_level' => $this->resource->students->groupBy('academic_year')->map(fn ($group, string|int|null $year): array => [
                    'year' => $year,
                    'formatted_year' => $this->formatAcademicYear($year),
                    'count' => $group->count(),
                ])->values(),
                'by_status' => $this->resource->students->groupBy('status')->map(fn ($group, $status): array => [
                    'status' => $status,
                    'count' => $group->count(),
                ])->values(),
            ]),

            // Timestamps
            'created_at' => format_timestamp($this->resource->created_at),
            'updated_at' => format_timestamp($this->resource->updated_at),
        ];
    }

    /**
     * Format academic year for display
     */
    private function formatAcademicYear(string|int|null $year): string
    {
        $yearStr = (string) $year;

        return match ($yearStr) {
            '1' => '1st Year',
            '2' => '2nd Year',
            '3' => '3rd Year',
            '4' => '4th Year',
            default => $yearStr ?: 'N/A',
        };
    }

    /**
     * Format semester for display
     */
    private function formatSemester(string|int|null $semester): string
    {
        return match ((string) $semester) {
            '1', '1st' => '1st Semester',
            '2', '2nd' => '2nd Semester',
            'summer' => 'Summer',
            default => (string) $semester ?: 'N/A',
        };
    }
}
