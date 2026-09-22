<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\StudentEnrollment;
use App\Models\SubjectEnrollment;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the subjects a student is enrolled in for an enrollment or academic term, including subject details, units, section, grades, and instructor.')]
#[IsReadOnly]
final class GetStudentSubjectEnrollmentsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?GeneralSettingsService $settings = null)
    {
        $this->settings ??= app(GeneralSettingsService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $enrollmentId = $request->get('enrollment_id') !== null ? (int) $request->get('enrollment_id') : null;

        if ($enrollmentId !== null) {
            $enrollment = StudentEnrollment::query()->with('student')->findOrFail($enrollmentId);

            if (! $enrollment->belongsToCurrentSchool()) {
                throw new \Illuminate\Auth\Access\AuthorizationException('The enrollment record does not belong to the selected school.');
            }

            if ($user->isStudentRole()) {
                $myStudent = app(\App\Services\ApiIdentityService::class)->studentFor($user);
                if (! $myStudent || (int) $myStudent->id !== (int) $enrollment->student_id) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('Students can only view their own subject enrollments.');
                }
            } else {
                $this->requirePermission($user, 'View:StudentEnrollment', 'You are not permitted to view student subject enrollments.');
            }

            $subjectEnrollments = SubjectEnrollment::query()
                ->where('enrollment_id', $enrollment->id)
                ->with(['subject', 'class.faculty', 'class.room'])
                ->get();

            $student = $enrollment->student;
            $schoolYear = $enrollment->school_year;
            $semester = (int) $enrollment->semester;
        } else {
            $studentId = $request->get('student_id') !== null ? (int) $request->get('student_id') : null;
            $student = $this->resolveStudentForCaller($user, $studentId);

            if (! $user->isStudentRole()) {
                $this->requirePermission($user, 'View:StudentEnrollment', 'You are not permitted to view student subject enrollments.');
            }

            $schoolYear = GeneralSettingsService::normalizeSchoolYear((string) ($request->get('school_year') ?? $this->settings->getCurrentSchoolYearString()));
            $semester = (int) ($request->get('semester') ?? $this->settings->getCurrentSemester());

            $subjectEnrollments = SubjectEnrollment::query()
                ->where('student_id', $student->id)
                ->where('semester', $semester)
                ->whereIn('school_year', [$schoolYear, str_replace(' ', '', $schoolYear)])
                ->with(['subject', 'class.faculty', 'class.room'])
                ->get();
        }

        $totalUnits = 0;
        $items = $subjectEnrollments->map(function (SubjectEnrollment $se) use (&$totalUnits): array {
            $subject = $se->subject;
            $units = $subject?->units ?? $se->external_subject_units ?? ($se->enrolled_lecture_units + $se->enrolled_laboratory_units);
            $totalUnits += (int) $units;

            return [
                'id' => $se->id,
                'subject_id' => $se->subject_id,
                'subject_code' => $subject?->code ?? $se->external_subject_code ?? 'N/A',
                'subject_title' => $subject?->title ?? $se->external_subject_title ?? 'N/A',
                'units' => $units,
                'grade' => $se->grade,
                'remarks' => $se->remarks,
                'section' => $se->section ?? $se->class?->section ?? 'N/A',
                'instructor' => $se->instructor ?? $se->class?->faculty?->full_name ?? 'TBA',
                'room' => $se->class?->room?->name ?? 'TBA',
                'class_id' => $se->class_id,
                'classification' => $se->classification,
                'is_credited' => (bool) $se->is_credited,
                'is_modular' => (bool) $se->is_modular,
            ];
        })->values()->all();

        return Response::structured([
            'student' => $student === null ? null : [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
            ],
            'term' => [
                'school_year' => $schoolYear,
                'semester' => $semester,
            ],
            'total_units' => $totalUnits,
            'subjects_count' => count($items),
            'subjects' => $items,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'enrollment_id' => $schema->integer()->min(1)->description('Target enrollment record ID. If provided, returns subjects in that enrollment.'),
            'student_id' => $schema->integer()->min(1)->description('The student database ID or student number. Optional for student callers.'),
            'school_year' => $schema->string()->description('Optional school year (e.g. "2026 - 2027"). Defaults to current school year when student_id is used.'),
            'semester' => $schema->integer()->enum([1, 2])->description('Optional semester (1 or 2). Defaults to current semester when student_id is used.'),
        ];
    }
}
