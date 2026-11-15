<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ClassEnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $studentView = $request->user()?->isStudentRole() === true;
        $service = app(\App\Services\GradeReleaseService::class);
        $published = $studentView && $this->student ? $service->publishedClass($this->resource, $service->releases($this->student)) : null;

        return [
            'id' => (int) $this->id,
            'class_id' => (int) $this->class_id,
            'student_id' => (int) $this->student_id,
            'status' => (bool) $this->status,
            'remarks' => $studentView ? null : $this->remarks,
            'grades' => [
                'prelim' => $this->prelim_grade,
                'midterm' => $this->midterm_grade,
                'finals' => $studentView ? ($published['finals_grade'] ?? null) : $this->finals_grade,
                'average' => $studentView ? ($published['total_average'] ?? null) : $this->total_average,
                'finalized' => $studentView ? null : (bool) $this->is_grades_finalized,
                'verified' => $studentView ? null : (bool) $this->is_grades_verified,
            ],
            'student' => $this->whenLoaded('student', fn (): ?array => $this->student ? (new StudentResource($this->student))->toArray($request) : null),
            'class' => $this->whenLoaded('class', fn (): ?array => $this->class ? (new ClassResource($this->class))->toArray($request) : null),
        ];
    }
}
