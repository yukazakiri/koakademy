<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ClassResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $subject = $this->subject ?? $this->Subject ?? $this->SubjectByCodeFallback ?? $this->ShsSubject;

        return [
            'id' => (int) $this->id,
            'subject_code' => $this->subject_code,
            'subject_title' => $subject?->title ?? $this->subject_title,
            'section' => $this->section,
            'academic_year' => $this->academic_year,
            'semester' => $this->semester,
            'school_year' => $this->school_year,
            'classification' => $this->classification,
            'grade_level' => $this->grade_level,
            'maximum_slots' => $this->maximum_slots,
            'students_count' => $this->when(isset($this->class_enrollments_count), (int) $this->class_enrollments_count),
            'faculty' => $this->whenLoaded('faculty', fn (): ?array => $this->faculty ? [
                'id' => (string) $this->faculty->id,
                'name' => $this->faculty->full_name,
            ] : null),
            'room' => $this->whenLoaded('room', fn (): ?array => $this->room ? [
                'id' => (int) $this->room->id,
                'name' => $this->room->name,
                'code' => $this->room->class_code,
            ] : null),
            'schedules' => $this->whenLoaded('schedules', fn (): array => $this->schedules->map(fn ($schedule): array => [
                'id' => (int) $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i'),
                'end_time' => $schedule->end_time?->format('H:i'),
                'room' => $schedule->room?->name,
            ])->values()->all()),
        ];
    }
}
