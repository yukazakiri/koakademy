<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\TimetableConflictService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

final class ManageClassScheduleTool implements Tool
{
    use InteractsWithApprovals;

    public function __construct(
        private ?GeneralSettingsService $settings = null,
        private ?TimetableConflictService $conflictService = null,
    ) {
        $this->settings ??= app(GeneralSettingsService::class);
        $this->conflictService ??= app(TimetableConflictService::class);
    }

    public function description(): Stringable|string
    {
        return 'Create class sections, reschedule timetable slots, assign rooms or instructors, and manage class offerings. Modifying schedules requires administrator confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => true, 'message' => 'Authentication is required.']);
        }

        $action = mb_strtolower((string) $request['action']);

        if ($action === 'get') {
            if (! $user->hasRole('super_admin') && ! $user->can('View:Classes')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to view classes.']);
            }
        } elseif ($action === 'create_class') {
            if (! $user->hasRole('super_admin') && ! $user->can('Create:Classes')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to create classes.']);
            }
        } elseif ($action === 'batch_create') {
            if (! $user->hasRole('super_admin') && ! $user->can('Create:Classes') && ! $user->can('Update:Classes')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to batch create class schedules.']);
            }
        } elseif ($action === 'delete_class') {
            if (! $user->hasRole('super_admin') && ! $user->can('Delete:Classes')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to delete classes.']);
            }
        } elseif (in_array($action, ['reschedule', 'update_schedule', 'assign_faculty', 'assign_room'], true)) {
            if (! $user->hasRole('super_admin') && ! $user->can('Update:Classes')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to update classes or schedules.']);
            }
        }

        return match ($action) {
            'create_class' => $this->handleCreateClass($request),
            'batch_create' => $this->handleBatchCreate($request),
            'reschedule', 'update_schedule' => $this->handleReschedule($request),
            'assign_faculty' => $this->handleAssignFaculty($request),
            'assign_room' => $this->handleAssignRoom($request),
            'delete_class' => $this->handleDeleteClass($request),
            'get' => $this->handleGet($request),
            default => json_encode(['error' => true, 'message' => "Unknown action '{$action}'. Supported: create_class, batch_create, reschedule, assign_faculty, assign_room, delete_class, get."]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create_class', 'batch_create', 'reschedule', 'assign_faculty', 'assign_room', 'delete_class', 'get'])
                ->required()
                ->description('Operation to perform on classes and schedules.'),
            'classes' => $schema->array()
                ->description('List of class schedules to batch create.')
                ->items(
                    $schema->object(fn ($s) => [
                        'subject_code' => $s->string()->required()->description('Subject code (e.g. CS101).'),
                        'section' => $s->string()->required()->description('Section (e.g. BSCS-1A).'),
                        'day_of_week' => $s->string()->description('Monday, Tuesday, Wednesday, Thursday, Friday, Saturday.'),
                        'start_time' => $s->string()->description('HH:MM format.'),
                        'end_time' => $s->string()->description('HH:MM format.'),
                        'room_name' => $s->string()->description('Room name or number.'),
                        'faculty_name' => $s->string()->description('Instructor name.'),
                        'maximum_slots' => $s->integer()->description('Max capacity (default 40).'),
                    ])
                ),
            'class_id' => $schema->integer()->description('Class ID (for reschedule, assign, delete, get).'),
            'schedule_id' => $schema->integer()->description('Specific schedule ID to modify (optional).'),
            'subject_code' => $schema->string()->description('Subject code for the class (e.g. "CS101").'),
            'section' => $schema->string()->description('Section name (e.g. "BSCS-1A").'),
            'day_of_week' => $schema->string()->description('Meeting day (Monday, Tuesday, Wednesday, Thursday, Friday, Saturday).'),
            'start_time' => $schema->string()->description('Start time in HH:MM format (e.g. "09:00", "13:30").'),
            'end_time' => $schema->string()->description('End time in HH:MM format (e.g. "10:30", "15:00").'),
            'room_id' => $schema->integer()->description('Room database ID.'),
            'room_name' => $schema->string()->description('Room name (e.g. "Room 201").'),
            'faculty_id' => $schema->string()->description('Faculty instructor ID or UUID.'),
            'faculty_name' => $schema->string()->description('Instructor name (e.g. "Dr. Santos").'),
            'maximum_slots' => $schema->integer()->description('Maximum student capacity (default 40).'),
            'school_year' => $schema->string()->description('Academic year (e.g. "2026-2027").'),
            'semester' => $schema->integer()->enum([1, 2])->description('Semester (1 or 2).'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $action = mb_strtolower((string) ($request['action'] ?? ''));

        if ($action === 'get') {
            return false;
        }

        if ($action === 'create_class') {
            $subj = $request['subject_code'] ?? 'Subject';
            $sec = $request['section'] ?? 'Section';
            $room = $request['room_name'] ?? ($request['room_id'] ?? 'TBA');
            $time = ($request['day_of_week'] ?? '').' '.($request['start_time'] ?? '').'-'.($request['end_time'] ?? '');

            return Approval::required("Create new class section {$subj} ({$sec}) in Room {$room} on {$time}?");
        }

        if ($action === 'batch_create') {
            $count = is_array($request['classes'] ?? null) ? count($request['classes']) : 0;

            return Approval::required("Batch create or schedule {$count} class offerings on the institutional timetable?");
        }

        if ($action === 'reschedule' || $action === 'update_schedule') {
            $classId = $request['class_id'] ?? 'Class';
            $time = ($request['day_of_week'] ?? '').' '.($request['start_time'] ?? '').'-'.($request['end_time'] ?? '');

            return Approval::required("Reschedule class #{$classId} to {$time}?");
        }

        if ($action === 'assign_faculty') {
            $classId = $request['class_id'] ?? 'Class';
            $faculty = $request['faculty_name'] ?? ($request['faculty_id'] ?? 'Faculty');

            return Approval::required("Assign instructor '{$faculty}' to class #{$classId}?");
        }

        if ($action === 'assign_room') {
            $classId = $request['class_id'] ?? 'Class';
            $room = $request['room_name'] ?? ($request['room_id'] ?? 'Room');

            return Approval::required("Reassign class #{$classId} to Room '{$room}'?");
        }

        if ($action === 'delete_class') {
            $classId = $request['class_id'] ?? 'Class';

            return Approval::required("Permanently delete class #{$classId} and its timetable schedules?");
        }

        return false;
    }

    private function handleCreateClass(Request $request): string
    {
        $validated = $request->validate([
            'subject_code' => 'required|string|max:50',
            'section' => 'required|string|max:50',
            'day_of_week' => 'nullable|string',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'room_id' => 'nullable|integer',
            'room_name' => 'nullable|string',
            'faculty_id' => 'nullable',
            'faculty_name' => 'nullable|string',
            'maximum_slots' => 'nullable|integer|between:1,150',
            'school_year' => 'nullable|string',
            'semester' => 'nullable|integer|in:1,2',
        ]);

        $schoolYear = $validated['school_year'] ?? $this->settings->getCurrentSchoolYearString();
        $semester = $validated['semester'] ?? $this->settings->getCurrentSemester();

        $roomId = $validated['room_id'] ?? null;
        if (! $roomId && filled($validated['room_name'] ?? null)) {
            $room = Room::query()->where('name', 'like', "%{$validated['room_name']}%")->first();
            $roomId = $room?->id;
        }

        $facultyId = null;
        if (filled($validated['faculty_id'] ?? null)) {
            $faculty = Faculty::query()->find($validated['faculty_id']);
            $facultyId = $faculty?->id;
        } elseif (filled($validated['faculty_name'] ?? null)) {
            $faculty = Faculty::query()->whereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) LIKE ?", ["%{$validated['faculty_name']}%"])->first();
            $facultyId = $faculty?->id;
        }

        $subject = Subject::query()->where('code', $validated['subject_code'])->first();

        try {
            return DB::transaction(function () use ($validated, $schoolYear, $semester, $roomId, $facultyId, $subject) {
                $class = Classes::query()->create([
                    'subject_code' => mb_strtoupper(mb_trim($validated['subject_code'])),
                    'section' => mb_trim($validated['section']),
                    'subject_id' => $subject?->id,
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                    'room_id' => $roomId,
                    'faculty_id' => $facultyId,
                    'maximum_slots' => (int) ($validated['maximum_slots'] ?? 40),
                ]);

                if (filled($validated['day_of_week'] ?? null) && filled($validated['start_time'] ?? null) && filled($validated['end_time'] ?? null)) {
                    $dayOfWeek = mb_convert_case(mb_trim((string) $validated['day_of_week']), MB_CASE_TITLE);
                    $startTime = Carbon::parse($validated['start_time'])->format('H:i:s');
                    $endTime = Carbon::parse($validated['end_time'])->format('H:i:s');

                    $this->guardScheduleConflicts($roomId, $facultyId, $dayOfWeek, $startTime, $endTime, $schoolYear, $semester);

                    Schedule::query()->create([
                        'class_id' => $class->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'room_id' => $roomId,
                    ]);
                }

                return json_encode([
                    'success' => true,
                    'action' => 'create_class',
                    'message' => "Successfully created class {$class->subject_code} ({$class->section}).",
                    'class' => [
                        'id' => $class->id,
                        'subject_code' => $class->subject_code,
                        'section' => $class->section,
                        'school_year' => $class->school_year,
                        'semester' => $class->semester,
                        'faculty' => $class->faculty?->full_name ?? 'TBA',
                        'room' => $class->room?->name ?? 'TBA',
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            });
        } catch (Throwable $e) {
            return json_encode(['error' => true, 'message' => "Failed to create class: {$e->getMessage()}"]);
        }
    }

    private function handleBatchCreate(Request $request): string
    {
        $validated = $request->validate([
            'classes' => 'required|array|min:1',
            'classes.*.subject_code' => 'required|string|max:50',
            'classes.*.section' => 'required|string|max:50',
            'classes.*.day_of_week' => 'nullable|string',
            'classes.*.start_time' => 'nullable|string',
            'classes.*.end_time' => 'nullable|string',
            'classes.*.room_id' => 'nullable|integer',
            'classes.*.room_name' => 'nullable|string',
            'classes.*.faculty_id' => 'nullable',
            'classes.*.faculty_name' => 'nullable|string',
            'classes.*.maximum_slots' => 'nullable|integer|between:1,150',
            'classes.*.school_year' => 'nullable|string',
            'classes.*.semester' => 'nullable|integer|in:1,2',
        ]);

        $defaultSchoolYear = $this->settings->getCurrentSchoolYearString();
        $defaultSemester = $this->settings->getCurrentSemester();

        $created = [];
        $updated = [];
        $warnings = [];

        DB::transaction(function () use ($validated, $defaultSchoolYear, $defaultSemester, &$created, &$updated, &$warnings) {
            foreach ($validated['classes'] as $cData) {
                $schoolYear = $cData['school_year'] ?? $defaultSchoolYear;
                $semester = $cData['semester'] ?? $defaultSemester;
                $subjectCode = mb_strtoupper(mb_trim((string) $cData['subject_code']));
                $section = mb_trim((string) $cData['section']);

                $roomId = $cData['room_id'] ?? null;
                if (! $roomId && filled($cData['room_name'] ?? null)) {
                    $room = Room::query()->where('name', 'like', "%{$cData['room_name']}%")->first();
                    $roomId = $room?->id;
                }

                $facultyId = null;
                if (filled($cData['faculty_id'] ?? null)) {
                    $faculty = Faculty::query()->find($cData['faculty_id']);
                    $facultyId = $faculty?->id;
                } elseif (filled($cData['faculty_name'] ?? null)) {
                    $faculty = Faculty::query()->whereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) LIKE ?", ["%{$cData['faculty_name']}%"])->first();
                    $facultyId = $faculty?->id;
                }

                $subject = Subject::query()->where('code', $subjectCode)->first();

                $class = Classes::query()
                    ->where('subject_code', $subjectCode)
                    ->where('section', $section)
                    ->where('school_year', $schoolYear)
                    ->where('semester', $semester)
                    ->first();

                if ($class instanceof Classes) {
                    $updates = array_filter([
                        'room_id' => $roomId,
                        'faculty_id' => $facultyId,
                        'maximum_slots' => $cData['maximum_slots'] ?? null,
                    ], fn ($v) => $v !== null);
                    if (! empty($updates)) {
                        $class->update($updates);
                    }
                    $updated[] = [
                        'id' => $class->id,
                        'subject_code' => $class->subject_code,
                        'section' => $class->section,
                    ];
                } else {
                    $class = Classes::query()->create([
                        'subject_code' => $subjectCode,
                        'section' => $section,
                        'subject_id' => $subject?->id,
                        'school_year' => $schoolYear,
                        'semester' => $semester,
                        'room_id' => $roomId,
                        'faculty_id' => $facultyId,
                        'maximum_slots' => (int) ($cData['maximum_slots'] ?? 40),
                    ]);
                    $created[] = [
                        'id' => $class->id,
                        'subject_code' => $class->subject_code,
                        'section' => $class->section,
                    ];
                }

                if (filled($cData['day_of_week'] ?? null) && filled($cData['start_time'] ?? null) && filled($cData['end_time'] ?? null)) {
                    $dayOfWeek = mb_convert_case(mb_trim((string) $cData['day_of_week']), MB_CASE_TITLE);
                    $startTime = Carbon::parse($cData['start_time'])->format('H:i:s');
                    $endTime = Carbon::parse($cData['end_time'])->format('H:i:s');

                    try {
                        $this->guardScheduleConflicts($roomId, $facultyId, $dayOfWeek, $startTime, $endTime, $schoolYear, $semester);
                    } catch (Throwable $conflictEx) {
                        $warnings[] = "{$subjectCode} ({$section}): ".$conflictEx->getMessage();
                    }

                    Schedule::query()->firstOrCreate([
                        'class_id' => $class->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                    ], [
                        'room_id' => $roomId,
                    ]);
                }
            }
        });

        return json_encode([
            'success' => true,
            'action' => 'batch_create',
            'created_classes_count' => count($created),
            'updated_classes_count' => count($updated),
            'created' => $created,
            'updated' => $updated,
            'warnings' => $warnings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleReschedule(Request $request): string
    {
        $validated = $request->validate([
            'class_id' => 'required|integer',
            'schedule_id' => 'nullable|integer',
            'day_of_week' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'room_id' => 'nullable|integer',
            'room_name' => 'nullable|string',
        ]);

        $class = Classes::query()->find($validated['class_id']);
        if (! $class instanceof Classes) {
            return json_encode(['error' => true, 'message' => "Class #{$validated['class_id']} not found."]);
        }

        $roomId = $validated['room_id'] ?? null;
        if (! $roomId && filled($validated['room_name'] ?? null)) {
            $room = Room::query()->where('name', 'like', "%{$validated['room_name']}%")->first();
            $roomId = $room?->id;
        }

        $dayOfWeek = mb_convert_case(mb_trim((string) $validated['day_of_week']), MB_CASE_TITLE);
        $startTime = Carbon::parse($validated['start_time'])->format('H:i:s');
        $endTime = Carbon::parse($validated['end_time'])->format('H:i:s');

        $schedule = null;
        if (filled($validated['schedule_id'] ?? null)) {
            $schedule = Schedule::query()->where('class_id', $class->id)->find($validated['schedule_id']);
        }

        if (! $schedule instanceof Schedule) {
            $schedule = Schedule::query()->where('class_id', $class->id)->first();
        }

        $effectiveRoomId = $roomId ?? $schedule?->room_id ?? $class->room_id;
        try {
            $this->guardScheduleConflicts($effectiveRoomId, $class->faculty_id, $dayOfWeek, $startTime, $endTime, $class->school_year, (int) $class->semester, $schedule?->id, $class->id);
        } catch (InvalidArgumentException $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()]);
        }

        if ($schedule instanceof Schedule) {
            $schedule->update([
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_id' => $roomId ?? $schedule->room_id,
            ]);
        } else {
            $schedule = Schedule::query()->create([
                'class_id' => $class->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_id' => $roomId ?? $class->room_id,
            ]);
        }

        return json_encode([
            'success' => true,
            'action' => 'reschedule',
            'message' => "Rescheduled class {$class->subject_code} ({$class->section}) to {$dayOfWeek} {$startTime}-{$endTime}.",
            'schedule' => [
                'id' => $schedule->id,
                'class_id' => $class->id,
                'day_of_week' => $schedule->day_of_week,
                'time_range' => "{$schedule->formatted_start_time} - {$schedule->formatted_end_time}",
                'room' => $schedule->room?->name ?? 'TBA',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleAssignFaculty(Request $request): string
    {
        $validated = $request->validate([
            'class_id' => 'required|integer',
            'faculty_id' => 'nullable',
            'faculty_name' => 'nullable|string',
        ]);

        $class = Classes::query()->find($validated['class_id']);
        if (! $class instanceof Classes) {
            return json_encode(['error' => true, 'message' => "Class #{$validated['class_id']} not found."]);
        }

        $faculty = null;
        if (filled($validated['faculty_id'] ?? null)) {
            $faculty = Faculty::query()->find($validated['faculty_id']);
        } elseif (filled($validated['faculty_name'] ?? null)) {
            $faculty = Faculty::query()->whereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) LIKE ?", ["%{$validated['faculty_name']}%"])->first();
        }

        if (! $faculty instanceof Faculty) {
            return json_encode(['error' => true, 'message' => 'Specified faculty member was not found.']);
        }

        $class->update(['faculty_id' => $faculty->id]);

        return json_encode([
            'success' => true,
            'action' => 'assign_faculty',
            'message' => "Assigned instructor {$faculty->full_name} to class {$class->subject_code} ({$class->section}).",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleAssignRoom(Request $request): string
    {
        $validated = $request->validate([
            'class_id' => 'required|integer',
            'room_id' => 'nullable|integer',
            'room_name' => 'nullable|string',
        ]);

        $class = Classes::query()->find($validated['class_id']);
        if (! $class instanceof Classes) {
            return json_encode(['error' => true, 'message' => "Class #{$validated['class_id']} not found."]);
        }

        $room = null;
        if (filled($validated['room_id'] ?? null)) {
            $room = Room::query()->find($validated['room_id']);
        } elseif (filled($validated['room_name'] ?? null)) {
            $room = Room::query()->where('name', 'like', "%{$validated['room_name']}%")->first();
        }

        if (! $room instanceof Room) {
            return json_encode(['error' => true, 'message' => 'Specified classroom was not found.']);
        }

        $class->update(['room_id' => $room->id]);
        Schedule::query()->where('class_id', $class->id)->update(['room_id' => $room->id]);

        return json_encode([
            'success' => true,
            'action' => 'assign_room',
            'message' => "Assigned room {$room->name} to class {$class->subject_code} ({$class->section}).",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleDeleteClass(Request $request): string
    {
        $classId = (int) $request['class_id'];
        $class = Classes::query()->find($classId);

        if (! $class instanceof Classes) {
            return json_encode(['error' => true, 'message' => "Class #{$classId} not found."]);
        }

        $enrolledCount = $class->class_enrollments()->count();
        if ($enrolledCount > 0 && ! ($request['force'] ?? false)) {
            return json_encode([
                'error' => true,
                'message' => "Cannot delete class {$class->subject_code} ({$class->section}) because {$enrolledCount} student(s) are currently enrolled. Pass force=true to override.",
            ]);
        }

        $sec = $class->section;
        $code = $class->subject_code;

        Schedule::query()->where('class_id', $class->id)->delete();
        $class->delete();

        return json_encode([
            'success' => true,
            'action' => 'delete_class',
            'message' => "Successfully deleted class {$code} ({$sec}) and removed its timetable schedules.",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleGet(Request $request): string
    {
        $classId = (int) $request['class_id'];
        $class = Classes::query()->with(['faculty', 'room', 'schedules.room', 'class_enrollments'])->find($classId);

        if (! $class instanceof Classes) {
            return json_encode(['error' => true, 'message' => "Class #{$classId} not found."]);
        }

        $schedules = $class->schedules->map(fn (Schedule $s): array => [
            'id' => $s->id,
            'day_of_week' => $s->day_of_week,
            'time_range' => "{$s->formatted_start_time} - {$s->formatted_end_time}",
            'room' => $s->room?->name ?? 'TBA',
        ])->all();

        return json_encode([
            'found' => true,
            'id' => $class->id,
            'subject_code' => $class->subject_code,
            'section' => $class->section,
            'instructor' => $class->faculty?->full_name ?? 'TBA',
            'room' => $class->room?->name ?? 'TBA',
            'school_year' => $class->school_year,
            'semester' => $class->semester,
            'enrolled_count' => $class->class_enrollments->count(),
            'maximum_slots' => $class->maximum_slots,
            'schedules' => $schedules,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
