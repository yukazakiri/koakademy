<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Handlers;

use App\Filament\Resources\Classes\Api\Transformers\ClassScheduleTransformer;
use App\Filament\Resources\Classes\ClassResource;
use App\Models\Classes;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class IndexHandler extends Handlers
{
    public static ?string $uri = '/schedules';

    public static ?string $resource = ClassResource::class;

    protected static string $permission = 'View:Class';

    /**
     * List All Class Schedules
     *
     * @return array<ClassScheduleTransformer>
     */
    public function handler(Request $request): array
    {
        $query = QueryBuilder::for(Classes::class)
            ->with([
                'Faculty' => function ($query): void {
                    $query->select('id', 'faculty_id_number', 'first_name', 'last_name', 'middle_name', 'email');
                },
                'Room' => function ($query): void {
                    $query->select('id', 'name', 'class_code');
                },
                'Schedule' => function ($query): void {
                    $query->select('id', 'class_id', 'day_of_week', 'start_time', 'end_time', 'room_id')
                        ->with('Room:id,name,class_code')
                        ->orderBy('day_of_week')
                        ->orderBy('start_time');
                },
                'Course' => function ($query): void {
                    $query->select('id', 'code', 'title');
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
            ->paginate();

        return [
            'data' => collect($query->items())->map(fn ($class): ClassScheduleTransformer => new ClassScheduleTransformer($class)),
            'pagination' => [
                'current_page' => $query->currentPage(),
                'last_page' => $query->lastPage(),
                'per_page' => $query->perPage(),
                'total' => $query->total(),
                'from' => $query->firstItem(),
                'to' => $query->lastItem(),
            ],
        ];
    }
}
