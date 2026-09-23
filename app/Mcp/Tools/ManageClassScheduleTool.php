<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Subject;
use App\Services\GeneralSettingsService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Manage class offerings and timetables: create class sections with meeting schedules, reschedule time slots, reassign rooms or instructors, or delete classes.')]
final class ManageClassScheduleTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?GeneralSettingsService $settings = null)
    {
        $this->settings ??= app(GeneralSettingsService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $action = mb_strtolower((string) $request->get('action'));

        if ($action === 'get') {
            $user = $this->requireRead($request);
            $this->requirePermission($user, 'View:Classes', 'You are not permitted to view classes.');

            $classId = (int) $request->get('class_id');
            $class = Classes::query()->with(['faculty', 'room', 'schedules.room', 'class_enrollments'])->find($classId);

            if (! $class instanceof Classes) {
                return Response::structured(['found' => false, 'message' => "Class #{$classId} not found."]);
            }

            return Response::structured([
                'found' => true,
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'section' => $class->section,
                'instructor' => $class->faculty?->full_name ?? 'TBA',
                'room' => $class->room?->name ?? 'TBA',
                'school_year' => $class->school_year,
                'semester' => $class->semester,
                'enrolled_count' => $class->class_enrollments->count(),
                'schedules' => $class->schedules->map(fn (Schedule $s) => [
                    'day' => $s->day_of_week,
                    'time' => "{$s->formatted_start_time} - {$s->formatted_end_time}",
                    'room' => $s->room?->name ?? 'TBA',
                ])->all(),
            ]);
        }

        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:Classes', 'You are not permitted to modify classes or timetables.');

        return match ($action) {
            'create_class' => $this->handleCreateClass($request),
            'reschedule' => $this->handleReschedule($request),
            'assign_faculty' => $this->handleAssignFaculty($request),
            'assign_room' => $this->handleAssignRoom($request),
            'delete_class' => $this->handleDeleteClass($request),
            default => Response::structured(['error' => true, 'message' => "Unsupported action '{$action}'."]),
        };
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create_class', 'reschedule', 'assign_faculty', 'assign_room', 'delete_class', 'get'])->required()->description('Operation.'),
            'class_id' => $schema->integer()->description('Class ID.'),
            'subject_code' => $schema->string()->description('Subject code (e.g. CS101).'),
            'section' => $schema->string()->description('Section code.'),
            'day_of_week' => $schema->string()->description('Meeting day.'),
            'start_time' => $schema->string()->description('Start time (HH:MM).'),
            'end_time' => $schema->string()->description('End time (HH:MM).'),
            'room_id' => $schema->integer()->description('Room ID.'),
            'faculty_id' => $schema->string()->description('Faculty ID or UUID.'),
        ];
    }

    private function handleCreateClass(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'subject_code' => ['required', 'string', 'max:50'],
            'section' => ['required', 'string', 'max:50'],
            'day_of_week' => ['nullable', 'string'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'room_id' => ['nullable', 'integer'],
            'faculty_id' => ['nullable'],
            'maximum_slots' => ['nullable', 'integer', 'between:1,150'],
            'school_year' => ['nullable', 'string'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $schoolYear = $validated['school_year'] ?? $this->settings->getCurrentSchoolYearString();
        $semester = $validated['semester'] ?? $this->settings->getCurrentSemester();
        $subject = Subject::query()->where('code', $validated['subject_code'])->first();

        $class = DB::transaction(function () use ($validated, $schoolYear, $semester, $subject) {
            $newClass = Classes::query()->create([
                'subject_code' => mb_strtoupper(mb_trim($validated['subject_code'])),
                'section' => mb_trim($validated['section']),
                'subject_id' => $subject?->id,
                'school_year' => $schoolYear,
                'semester' => $semester,
                'room_id' => $validated['room_id'] ?? null,
                'faculty_id' => $validated['faculty_id'] ?? null,
                'maximum_slots' => (int) ($validated['maximum_slots'] ?? 40),
            ]);

            if (filled($validated['day_of_week'] ?? null) && filled($validated['start_time'] ?? null) && filled($validated['end_time'] ?? null)) {
                $dayOfWeek = mb_convert_case(mb_trim((string) $validated['day_of_week']), MB_CASE_TITLE);
                $startTime = Carbon::parse($validated['start_time'])->format('H:i:s');
                $endTime = Carbon::parse($validated['end_time'])->format('H:i:s');

                $this->guardScheduleConflicts($validated['room_id'] ?? null, $validated['faculty_id'] ?? null, $dayOfWeek, $startTime, $endTime, $schoolYear, $semester);

                Schedule::query()->create([
                    'class_id' => $newClass->id,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'room_id' => $validated['room_id'] ?? null,
                ]);
            }

            return $newClass;
        });

        return Response::structured([
            'success' => true,
            'action' => 'create_class',
            'class' => [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'section' => $class->section,
                'school_year' => $class->school_year,
                'semester' => $class->semester,
            ],
        ]);
    }

    private function handleReschedule(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer'],
            'day_of_week' => ['required', 'string'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'room_id' => ['nullable', 'integer'],
        ]);

        $class = Classes::query()->find($validated['class_id']);
        if (! $class instanceof Classes) {
            return Response::structured(['error' => true, 'message' => "Class #{$validated['class_id']} not found."]);
        }

        $dayOfWeek = mb_convert_case(mb_trim((string) $validated['day_of_week']), MB_CASE_TITLE);
        $startTime = Carbon::parse($validated['start_time'])->format('H:i:s');
        $endTime = Carbon::parse($validated['end_time'])->format('H:i:s');

        $schedule = Schedule::query()->where('class_id', $class->id)->first();
        $effectiveRoomId = $validated['room_id'] ?? $schedule?->room_id ?? $class->room_id;

        $this->guardScheduleConflicts($effectiveRoomId, $class->faculty_id, $dayOfWeek, $startTime, $endTime, $class->school_year, (int) $class->semester, $schedule?->id, $class->id);

        if ($schedule instanceof Schedule) {
            $schedule->update([
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_id' => $validated['room_id'] ?? $schedule->room_id,
            ]);
        } else {
            $schedule = Schedule::query()->create([
                'class_id' => $class->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_id' => $validated['room_id'] ?? $class->room_id,
            ]);
        }

        return Response::structured([
            'success' => true,
            'action' => 'reschedule',
            'message' => "Rescheduled {$class->subject_code} to {$dayOfWeek} {$startTime}-{$endTime}.",
            'schedule_id' => $schedule->id,
        ]);
    }

    private function handleAssignFaculty(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer'],
            'faculty_id' => ['required'],
        ]);

        $class = Classes::query()->find($validated['class_id']);
        $faculty = Faculty::query()->find($validated['faculty_id']);

        if (! $class instanceof Classes || ! $faculty instanceof Faculty) {
            return Response::structured(['error' => true, 'message' => 'Class or faculty not found.']);
        }

        $class->update(['faculty_id' => $faculty->id]);

        return Response::structured([
            'success' => true,
            'action' => 'assign_faculty',
            'message' => "Assigned {$faculty->full_name} to {$class->subject_code} ({$class->section}).",
        ]);
    }

    private function handleAssignRoom(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer'],
            'room_id' => ['required', 'integer'],
        ]);

        $class = Classes::query()->find($validated['class_id']);
        $room = Room::query()->find($validated['room_id']);

        if (! $class instanceof Classes || ! $room instanceof Room) {
            return Response::structured(['error' => true, 'message' => 'Class or room not found.']);
        }

        $class->update(['room_id' => $room->id]);
        Schedule::query()->where('class_id', $class->id)->update(['room_id' => $room->id]);

        return Response::structured([
            'success' => true,
            'action' => 'assign_room',
            'message' => "Assigned {$room->name} to {$class->subject_code} ({$class->section}).",
        ]);
    }

    private function handleDeleteClass(Request $request): ResponseFactory
    {
        $class = Classes::query()->find((int) $request->get('class_id'));
        if (! $class instanceof Classes) {
            return Response::structured(['error' => true, 'message' => 'Class not found.']);
        }

        $enrolledCount = $class->class_enrollments()->count();
        $force = (bool) $request->get('force', false);
        if ($enrolledCount > 0 && ! $force) {
            return Response::structured([
                'error' => true,
                'message' => "Cannot delete class {$class->subject_code} ({$class->section}) because {$enrolledCount} student(s) are currently enrolled. Pass force=true to override.",
            ]);
        }

        Schedule::query()->where('class_id', $class->id)->delete();
        $class->delete();

        return Response::structured([
            'success' => true,
            'action' => 'delete_class',
            'message' => "Successfully deleted class {$class->subject_code} ({$class->section}).",
        ]);
    }

    private function guardScheduleConflicts(
        ?int $roomId,
        mixed $facultyId,
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        string $schoolYear,
        int $semester,
        ?int $excludeScheduleId = null,
        ?int $excludeClassId = null
    ): void {
        if ($roomId) {
            $roomConflict = Schedule::query()
                ->where('room_id', $roomId)
                ->where('day_of_week', $dayOfWeek)
                ->when($excludeScheduleId, fn ($q) => $q->whereKeyNot($excludeScheduleId))
                ->whereHas('class', function ($q) use ($schoolYear, $semester, $excludeClassId) {
                    $q->forAcademicPeriod($schoolYear, $semester);
                    if ($excludeClassId) {
                        $q->whereKeyNot($excludeClassId);
                    }
                })
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where(fn ($sub) => $sub->where('start_time', '<=', $startTime)->where('end_time', '>', $startTime))
                        ->orWhere(fn ($sub) => $sub->where('start_time', '<', $endTime)->where('end_time', '>=', $endTime))
                        ->orWhere(fn ($sub) => $sub->where('start_time', '>=', $startTime)->where('end_time', '<=', $endTime));
                })
                ->first();

            if ($roomConflict instanceof Schedule) {
                $roomName = $roomConflict->room?->name ?? "Room #{$roomId}";
                $conflictingClass = $roomConflict->class ? "{$roomConflict->class->subject_code} ({$roomConflict->class->section})" : 'another class';
                throw new InvalidArgumentException("Room conflict detected: {$roomName} is already booked by {$conflictingClass} on {$dayOfWeek} {$roomConflict->formatted_start_time}-{$roomConflict->formatted_end_time}.");
            }
        }

        if ($facultyId) {
            $facultyConflict = Schedule::query()
                ->where('day_of_week', $dayOfWeek)
                ->when($excludeScheduleId, fn ($q) => $q->whereKeyNot($excludeScheduleId))
                ->whereHas('class', function ($q) use ($facultyId, $schoolYear, $semester, $excludeClassId) {
                    $q->where('faculty_id', $facultyId)->forAcademicPeriod($schoolYear, $semester);
                    if ($excludeClassId) {
                        $q->whereKeyNot($excludeClassId);
                    }
                })
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where(fn ($sub) => $sub->where('start_time', '<=', $startTime)->where('end_time', '>', $startTime))
                        ->orWhere(fn ($sub) => $sub->where('start_time', '<', $endTime)->where('end_time', '>=', $endTime))
                        ->orWhere(fn ($sub) => $sub->where('start_time', '>=', $startTime)->where('end_time', '<=', $endTime));
                })
                ->first();

            if ($facultyConflict instanceof Schedule) {
                $facultyName = $facultyConflict->class?->faculty?->full_name ?? 'Instructor';
                $conflictingClass = $facultyConflict->class ? "{$facultyConflict->class->subject_code} ({$facultyConflict->class->section})" : 'another class';
                throw new InvalidArgumentException("Faculty conflict detected: {$facultyName} is already assigned to {$conflictingClass} on {$dayOfWeek} {$facultyConflict->formatted_start_time}-{$facultyConflict->formatted_end_time}.");
            }
        }
    }
}
