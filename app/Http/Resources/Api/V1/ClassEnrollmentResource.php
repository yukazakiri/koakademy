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
        return [
            'id' => (int) $this->id,
            'class_id' => (int) $this->class_id,
            'student_id' => (int) $this->student_id,
            'status' => (bool) $this->status,
            'remarks' => $this->remarks,
            'grades' => [
                'prelim' => $this->prelim_grade,
                'midterm' => $this->midterm_grade,
                'finals' => $this->finals_grade,
                'average' => $this->total_average,
                'finalized' => (bool) $this->is_grades_finalized,
                'verified' => (bool) $this->is_grades_verified,
            ],
            'student' => $this->whenLoaded('student', fn (): ?array => $this->student ? (new StudentResource($this->student))->toArray($request) : null),
            'class' => $this->whenLoaded('class', fn (): ?array => $this->class ? (new ClassResource($this->class))->toArray($request) : null),
        ];
    }
}
