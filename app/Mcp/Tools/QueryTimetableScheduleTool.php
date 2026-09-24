<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Services\Ai\ComprehensiveScheduleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Query detailed timetable schedules and availability for specific classrooms/rooms, students, teachers/faculty members, or classes/subjects in the selected school.')]
#[IsReadOnly]
final class QueryTimetableScheduleTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?ComprehensiveScheduleService $scheduleService = null)
    {
        $this->scheduleService ??= app(ComprehensiveScheduleService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'ViewAny:ClassSchedule', 'You are not permitted to view institutional timetable schedules.');

        $validated = $request->validate([
            'target_type' => ['nullable', 'string', 'in:auto,room,student,faculty,class'],
            'identifier' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'integer'],
            'subject_code' => ['nullable', 'string', 'max:50'],
            'section' => ['nullable', 'string', 'max:50'],
            'day_of_week' => ['nullable', 'string', 'max:20'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'check_availability' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $classId = isset($validated['class_id']) ? (int) $validated['class_id'] : null;
        if (! $classId && is_numeric($validated['identifier'] ?? null)) {
            $classId = (int) $validated['identifier'];
        }

        $results = $this->scheduleService->query(
            targetType: $validated['target_type'] ?? 'auto',
            identifier: $validated['identifier'] ?? null,
            dayOfWeek: $validated['day_of_week'] ?? null,
            schoolYear: $validated['school_year'] ?? null,
            semester: isset($validated['semester']) ? (int) $validated['semester'] : null,
            checkAvailability: (bool) ($validated['check_availability'] ?? false),
            limit: (int) ($validated['limit'] ?? 15),
            classId: $classId,
            subjectCode: $validated['subject_code'] ?? null,
            section: $validated['section'] ?? null,
        );

        return Response::structured($results);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_type' => $schema->string()
                ->enum(['auto', 'room', 'student', 'faculty', 'class'])
                ->description("Type of schedule query: 'room' for classroom schedules/availability, 'student' for student's enrolled timetable & conflicts, 'faculty' for teacher's schedule, 'class' for subject section, or 'auto'."),
            'identifier' => $schema->string()
                ->description('Name, ID, code, or number of the target (e.g. "Room 204", "Maria Santos", "2024-0012", "Dr. Reyes", "CS101", "GE-3 Section B", "856").'),
            'class_id' => $schema->integer()
                ->description('Optional specific numeric class ID (e.g. 856).'),
            'subject_code' => $schema->string()
                ->description('Optional specific subject code (e.g. "GE-3", "CS101").'),
            'section' => $schema->string()
                ->description('Optional specific section name (e.g. "B", "BSCS-1A").'),
            'day_of_week' => $schema->string()
                ->description('Optional filter by day of week (e.g. Monday, Tuesday, Wednesday, Thursday, Friday, Saturday).'),
            'school_year' => $schema->string()
                ->description('Optional school year (e.g. 2026-2027). Defaults to current academic period.'),
            'semester' => $schema->integer()
                ->enum([1, 2])
                ->description('Optional semester (1 or 2). Defaults to current academic period.'),
            'check_availability' => $schema->boolean()
                ->description('For room queries: whether to calculate and return open/available time intervals.'),
        ];
    }
}
