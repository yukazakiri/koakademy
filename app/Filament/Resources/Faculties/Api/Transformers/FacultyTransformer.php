<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Transformers;

use App\Models\Faculty;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Faculty $resource
 */
final class FacultyTransformer extends JsonResource
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
            'faculty_id_number' => $this->resource->faculty_id_number,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'middle_name' => $this->resource->middle_name,
            'full_name' => $this->resource->full_name,
            'email' => $this->resource->email,
            'phone_number' => $this->resource->phone_number,
            'department' => $this->resource->department,
            'office_hours' => $this->resource->office_hours,
            'birth_date' => $this->resource->birth_date?->format('Y-m-d'),
            'address_line1' => $this->resource->address_line1,
            'biography' => $this->resource->biography,
            'education' => $this->resource->education,
            'courses_taught' => $this->resource->courses_taught,
            'photo_url' => $this->resource->photo_url,
            'status' => $this->resource->status,
            'gender' => $this->resource->gender,
            'age' => $this->resource->age,
            'created_at' => $this->resource->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->resource->updated_at?->format('Y-m-d H:i:s'),

            // Relations
            'classes' => $this->when($this->resource->relationLoaded('classes'), fn () => $this->resource->classes->map(fn ($class): array => [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'subject_title' => $class->subject_title,
                'section' => $class->section,
                'school_year' => $class->school_year,
                'semester' => $class->semester,
                'classification' => $class->classification,
                'maximum_slots' => $class->maximum_slots,
                'grade_level' => $class->grade_level,
                'student_count' => $class->student_count ?? 0,
                'display_info' => $class->display_info,
            ])),

            'account' => $this->when($this->resource->relationLoaded('account'), fn () => $this->resource->account?->only([
                'id',
                'person_id',
                'person_type',
                'username',
                'email',
                'role',
                'is_active',
            ])),

            'department_relation' => $this->when($this->resource->relationLoaded('departmentBelongsTo'), fn () => $this->resource->departmentBelongsTo?->only([
                'id',
                'code',
                'name',
                'college_id',
            ])),

            'class_enrollments_count' => $this->when($this->resource->relationLoaded('classEnrollments'), $this->resource->classEnrollments->count(...)),

            'classes_count' => $this->when($this->resource->relationLoaded('classes'), $this->resource->classes->count(...)),
        ];
    }
}
