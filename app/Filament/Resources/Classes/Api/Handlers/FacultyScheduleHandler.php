<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Handlers;

use App\Filament\Resources\Classes\Api\Transformers\ClassScheduleTransformer;
use App\Filament\Resources\Classes\ClassesResource;
// use App\Filament\Resources\Classes\ClassResource;
use App\Models\Classes;
use App\Models\Faculty;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class FacultyScheduleHandler extends Handlers
{
    public static ?string $uri = '/faculty/{faculty_id}/schedules';

    public static ?string $resource = ClassesResource::class;

    protected static string $permission = 'View:Classes';

    /**
     * Get All Schedules for a Faculty Member
     *
     * @return array<ClassScheduleTransformer>
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse|array
    {
        $facultyIdentifier = $request->route('faculty_id');

        // Determine if the identifier is a UUID or faculty_id_number
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $facultyIdentifier)) {
            $faculty = Faculty::query()->find($facultyIdentifier);
        } else {
            $faculty = Faculty::query()->where('faculty_id_number', $facultyIdentifier)->first();
        }

        if (! $faculty) {
            return self::sendNotFoundResponse('Faculty not found');
        }

        // Get all classes for this faculty member
        $classes = QueryBuilder::for(Classes::class)
            ->where('faculty_id', $faculty->id)
            ->with([
                'Faculty' => function ($query): void {
                    $query->select('id', 'faculty_id_number', 'first_name', 'last_name', 'middle_name', 'email');
                },
                'Room' => function ($query): void {
                    $query->select('id', 'name', 'class_code');
                },
                'schedules' => function ($query): void {
                    $query->select('id', 'class_id', 'day_of_week', 'start_time', 'end_time', 'room_id')
                        ->with('room:id,name,class_code')
                        ->orderBy('day_of_week')
                        ->orderBy('start_time');
                },
                'Subject' => function ($query): void {
                    $query->select('id', 'code', 'title');
                },
            ])
            ->withCount('class_enrollments')
            ->allowedFilters([
                'section',
                'subject_code',
                'classification',
                'academic_year',
                'semester',
                'school_year',
            ])
            ->allowedSorts([
                'section',
                'subject_code',
                'academic_year',
                'semester',
                'school_year',
                'class_enrollments_count',
            ])
            ->get();

        // Transform classes using the ClassScheduleTransformer
        $transformedClasses = ClassScheduleTransformer::collection($classes);

        // Organize schedules by day of week
        $organizedSchedules = $this->organizeSchedulesByDay($classes);

        // Count classes with schedules
        $classesWithSchedules = $classes->filter(fn ($class) => $class->schedules->isNotEmpty())->count();

        return [
            'faculty' => [
                'id' => $faculty->id,
                'faculty_id_number' => $faculty->faculty_id_number,
                'full_name' => $faculty->full_name,
                'email' => $faculty->email,
            ],
            'classes' => $transformedClasses,
            'organized_schedules' => $organizedSchedules,
            'total_classes' => $classes->count(),
            'total_scheduled_classes' => $classesWithSchedules,
        ];
    }

    /**
     * Organize class schedules by day of week
     *
     * @param  \Illuminate\Support\Collection<int, Classes>  $classes
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function organizeSchedulesByDay($classes): array
    {
        $daysOfWeek = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ];

        $organized = [];

        foreach ($daysOfWeek as $day) {
            $dayClasses = [];

            foreach ($classes as $class) {
                $classSchedules = $class->schedules->where('day_of_week', $day);

                if ($classSchedules->isNotEmpty()) {
                    foreach ($classSchedules as $schedule) {
                        $dayClasses[] = [
                            'class_id' => $class->id,
                            'section' => $class->section,
                            'subject_code' => $class->subject_code,
                            'subject_title' => $class->Subject?->title ?? $class->subject_code,
                            'academic_year' => $class->academic_year,
                            'semester' => $class->semester,
                            'schedule' => [
                                'id' => $schedule->id,
                                'start_time' => $schedule->start_time?->format('H:i'),
                                'end_time' => $schedule->end_time?->format('H:i'),
                                'time_range' => $schedule->start_time && $schedule->end_time
                                    ? $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i')
                                    : null,
                                'room' => [
                                    'id' => $schedule->room?->id,
                                    'name' => $schedule->room?->name,
                                    'code' => $schedule->room?->class_code,
                                ],
                            ],
                        ];
                    }
                }
            }

            // Sort by start time
            usort($dayClasses, fn (array $a, array $b): int => strcmp($a['schedule']['start_time'] ?? '', $b['schedule']['start_time'] ?? ''));

            $organized[mb_strtolower($day)] = $dayClasses;
        }

        return $organized;
    }
}
