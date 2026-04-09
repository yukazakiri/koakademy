<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Transformers;

use App\Models\Classes;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Classes $resource
 */
final class ClassScheduleTransformer extends JsonResource
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
            'class_id' => $this->resource->id,
            'section' => $this->resource->section,
            'subject_code' => $this->resource->subject_code,
            'subject_title' => $this->resource->Subject?->title ?? $this->resource->subject_code,
            'subjects' => $this->when($this->resource->subjects, function () {
                if ($this->resource->subjects->isEmpty()) {
                    return null;
                }

                return $this->resource->subjects->map(fn ($subject): array => [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'title' => $subject->title,
                ]);
            }),
            'classification' => $this->resource->classification ?? 'college',
            'academic_year' => $this->resource->academic_year,
            'semester' => $this->resource->semester,
            'school_year' => $this->resource->school_year,
            'grade_level' => $this->resource->grade_level,
            'maximum_slots' => $this->resource->maximum_slots,
            'enrolled_students' => $this->resource->class_enrollments_count ?? ($this->resource->class_enrollments?->count() ?? 0),
            'available_slots' => max(0, $this->resource->maximum_slots - ($this->resource->class_enrollments_count ?? ($this->resource->class_enrollments?->count() ?? 0))),

            'faculty' => [
                'id' => $this->resource->Faculty?->id,
                'faculty_id_number' => $this->resource->Faculty?->faculty_id_number,
                'full_name' => $this->resource->Faculty?->full_name,
                'email' => $this->resource->Faculty?->email,
            ],

            'room' => [
                'id' => $this->resource->Room?->id,
                'name' => $this->resource->Room?->name,
                'code' => $this->resource->Room?->class_code,
            ],

            'courses' => $this->when($this->resource->courses, fn () => $this->resource->courses->map(fn ($course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
            ])),

            'schedules' => $this->when($this->resource->Schedule, fn () => $this->resource->Schedule->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i'),
                'end_time' => $schedule->end_time?->format('H:i'),
                'time_range' => $schedule->start_time && $schedule->end_time
                    ? $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i')
                    : null,
                'room' => [
                    'id' => $schedule->Room?->id,
                    'name' => $schedule->Room?->name,
                    'code' => $schedule->Room?->class_code,
                ],
                'formatted_schedule' => $schedule->day_of_week && $schedule->start_time && $schedule->end_time
                    ? sprintf(
                        '%s: %s - %s (%s)',
                        $schedule->day_of_week,
                        $schedule->start_time->format('H:i'),
                        $schedule->end_time->format('H:i'),
                        $schedule->Room?->name ?? 'TBA'
                    )
                    : null,
            ])->values() ?? []),

            'weekly_schedule' => $this->formatWeeklySchedule($this->resource->Schedule ?? collect()),

            'faculty_schedules' => $this->resource->faculty_schedules ?? [],

            'created_at' => format_timestamp($this->resource->created_at),
            'updated_at' => format_timestamp($this->resource->updated_at),
        ];
    }

    /**
     * Format schedules by day of week
     *
     * @return array<string, mixed>
     */
    private function formatWeeklySchedule($schedules): array
    {
        $daysOfWeek = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ];

        $formattedSchedule = [];

        foreach ($daysOfWeek as $day) {
            $daySchedules = $schedules->where('day_of_week', $day)->values();

            $formattedSchedule[mb_strtolower($day)] = $daySchedules->map(fn ($schedule): array => [
                'start_time' => $schedule->start_time?->format('H:i'),
                'end_time' => $schedule->end_time?->format('H:i'),
                'time_range' => $schedule->start_time && $schedule->end_time
                    ? $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i')
                    : null,
                'room' => [
                    'id' => $schedule->Room?->id,
                    'name' => $schedule->Room?->name,
                ],
            ])->toArray();
        }

        return $formattedSchedule;
    }
}
