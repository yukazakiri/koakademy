<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Transformers;

use App\Models\Classes;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Classes $resource
 */
final class ClassesTransformer extends JsonResource
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
            // Basic class fields matching database structure
            'id' => $this->resource->id,
            'subject_code' => $this->resource->subject_code,
            'faculty_id' => $this->resource->faculty_id,
            'academic_year' => $this->resource->academic_year,
            'semester' => $this->resource->semester,
            'schedule_id' => $this->resource->schedule_id,
            'school_year' => $this->resource->school_year,
            'course_codes' => $this->resource->course_codes,
            'section' => $this->resource->section,
            'room_id' => $this->resource->room_id,
            'classification' => $this->resource->classification,
            'maximum_slots' => $this->resource->maximum_slots,
            'shs_track_id' => $this->resource->shs_track_id,
            'shs_strand_id' => $this->resource->shs_strand_id,
            'grade_level' => $this->resource->grade_level,
            'subject_id' => $this->resource->subject_id,
            'subject_ids' => $this->resource->subject_ids,

            // Relationships
            'subject' => $this->resource->active_subject ? [
                'code' => $this->resource->active_subject->code,
                'title' => $this->resource->active_subject->title,
            ] : null,
            'room' => $this->resource->Room ? [
                'id' => $this->resource->Room->id,
                'name' => $this->resource->Room->name,
            ] : null,
            'faculty' => $this->resource->Faculty ? [
                'id' => $this->resource->Faculty->id,
                'full_name' => $this->resource->Faculty->full_name,
            ] : null,
            'schedules' => $this->resource->schedules->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'room_id' => $schedule->room_id,
                'room_name' => $schedule->room?->name,
                'formatted_time' => $schedule->formatted_time,
            ]),

            'settings' => $this->resource->settings ?? Classes::getDefaultSettings(),

            // Timestamps
            'created_at' => format_timestamp($this->resource->created_at),
            'updated_at' => format_timestamp($this->resource->updated_at),
        ];
    }
}
