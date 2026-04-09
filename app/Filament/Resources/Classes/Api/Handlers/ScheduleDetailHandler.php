<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Handlers;

use App\Filament\Resources\Classes\Api\Transformers\ClassScheduleTransformer;
use App\Filament\Resources\Classes\ClassResource;
use App\Models\Classes;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

final class ScheduleDetailHandler extends Handlers
{
    public static ?string $uri = '/{id}/schedule';

    // public static ?string $resource = ClassResource::class;

    protected static string $permission = 'View:Classes';

    /**
     * Get Class Schedule Details
     *
     * @return ClassScheduleTransformer
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse|ClassScheduleTransformer
    {
        $classId = $request->route('id');

        $class = Classes::query()
            ->with([
                'Faculty' => function ($query): void {
                    $query->select('id', 'faculty_id_number', 'first_name', 'last_name', 'middle_name', 'email');
                },
                'Room' => function ($query): void {
                    $query->select('id', 'name', 'class_code');
                },
                'Schedule' => function ($query): void {
                    $query->select('id', 'class_id', 'day_of_week', 'start_time', 'end_time', 'room_id')
                        ->with('Room:id,name,class_code');
                },
                'Subject' => function ($query): void {
                    $query->select('id', 'code', 'title');
                },
                'courses' => function ($query): void {
                    $query->select('id', 'code', 'title');
                },
                'class_enrollments' => function ($query): void {
                    $query->whereNull('deleted_at');
                },
            ])
            ->where('id', $classId)
            ->first();

        if (! $class) {
            return self::sendNotFoundResponse('Class not found');
        }

        // Get all faculty schedules that overlap with this class's schedules
        $facultyScheduleConflict = $this->getFacultyScheduleConflicts($class);

        $class->faculty_schedules = $facultyScheduleConflict;

        return new ClassScheduleTransformer($class);
    }

    /**
     * Get faculty schedules that conflict or are scheduled for this class
     */
    private function getFacultyScheduleConflicts(Classes $class): array
    {
        if (! $class->Faculty) {
            return [];
        }

        $facultyId = $class->Faculty->id;

        // Get all class schedules for this faculty member
        $facultyClasses = Classes::query()
            ->where('faculty_id', $facultyId)
            ->where('id', '!=', $class->id) // Exclude the current class
            ->with(['Schedule', 'Subject', 'Room'])
            ->get();

        $scheduleConflicts = [];

        foreach ($facultyClasses as $facultyClass) {
            foreach ($facultyClass->Schedule as $schedule) {
                // Check if this schedule conflicts with any of the current class's schedules
                $hasConflict = $class->Schedule->contains(fn ($classSchedule): bool => $classSchedule->day_of_week === $schedule->day_of_week &&
                       $this->timesOverlap(
                           $classSchedule->start_time,
                           $classSchedule->end_time,
                           $schedule->start_time,
                           $schedule->end_time
                       ));

                if ($hasConflict) {
                    $scheduleConflicts[] = [
                        'class_id' => $facultyClass->id,
                        'class' => [
                            'id' => $facultyClass->id,
                            'section' => $facultyClass->section,
                            'subject_code' => $facultyClass->subject_code,
                            'subject_title' => $facultyClass->Subject?->title,
                        ],
                        'schedule' => [
                            'day_of_week' => $schedule->day_of_week,
                            'start_time' => $schedule->start_time->format('H:i'),
                            'end_time' => $schedule->end_time->format('H:i'),
                            'time_range' => $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i'),
                            'room' => $schedule->Room?->name ?? 'TBA',
                        ],
                        'has_conflict' => $hasConflict,
                    ];
                }
            }
        }

        return $scheduleConflicts;
    }

    /**
     * Check if two time ranges overlap
     */
    private function timesOverlap($start1, $end1, $start2, $end2): bool
    {
        // Convert Carbon instances to timestamps
        $start1 = $start1 instanceof \Carbon\Carbon ? $start1->timestamp : strtotime((string) $start1);
        $end1 = $end1 instanceof \Carbon\Carbon ? $end1->timestamp : strtotime((string) $end1);
        $start2 = $start2 instanceof \Carbon\Carbon ? $start2->timestamp : strtotime((string) $start2);
        $end2 = $end2 instanceof \Carbon\Carbon ? $end2->timestamp : strtotime((string) $end2);

        return $start1 < $end2 && $start2 < $end1;
    }
}
