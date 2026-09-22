<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Subject;
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

#[Description('Get the curriculum subjects a student is eligible to enroll in for a term, identifying completed, currently enrolled, and pending subjects.')]
#[IsReadOnly]
final class GetAvailableSubjectsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?GeneralSettingsService $settings = null)
    {
        $this->settings ??= app(GeneralSettingsService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $studentId = $request->get('student_id') !== null ? (int) $request->get('student_id') : null;
        $student = $this->resolveStudentForCaller($user, $studentId);

        if (! $user->isStudentRole()) {
            $this->requirePermission($user, 'View:Student', 'You are not permitted to view student subject eligibility.');
        }

        if (! $student->course_id) {
            return Response::structured([
                'student' => ['id' => $student->id, 'name' => $student->full_name],
                'message' => 'The student is not assigned to an active degree program or course.',
                'available_subjects' => [],
            ]);
        }

        $semester = (int) ($request->get('semester') ?? $this->settings->getCurrentSemester());
        $yearLevel = $request->get('year_level') !== null ? (int) $request->get('year_level') : (int) $student->academic_year;

        // Fetch student's prior subject enrollment records with subject details
        $enrolledSubjectRecords = SubjectEnrollment::query()
            ->with('subject')
            ->where('student_id', $student->id)
            ->get(['id', 'subject_id', 'grade', 'grade_outcome', 'remarks', 'semester', 'school_year']);

        $enrolledSubjectIds = $enrolledSubjectRecords->pluck('subject_id')->filter()->unique()->all();

        $gradingSystem = app(\App\Services\GradingSystemService::class);
        $school = $this->school();
        $gradingConfig = $gradingSystem->ensureConfig($school);

        $passedSubjectIds = [];
        $passedSubjectCodes = [];

        foreach ($enrolledSubjectRecords as $record) {
            $isPassed = $record->grade_outcome === 'pass'
                || (is_string($record->remarks) && strcasecmp(mb_trim($record->remarks), 'passed') === 0)
                || $gradingSystem->isPassingGrade($record->grade, $gradingConfig);

            if ($isPassed) {
                if ($record->subject_id) {
                    $passedSubjectIds[] = (int) $record->subject_id;
                }
                if ($record->subject?->code) {
                    $passedSubjectCodes[] = mb_strtoupper(mb_trim((string) $record->subject->code));
                }
            }
        }

        // Get subjects for this course
        $subjects = Subject::query()
            ->where('course_id', $student->course_id)
            ->when($yearLevel > 0, fn ($q) => $q->where('academic_year', $yearLevel))
            ->when($semester > 0, fn ($q) => $q->where('semester', $semester))
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->orderBy('code')
            ->get();

        $curriculum = $subjects->map(function (Subject $subject) use ($enrolledSubjectRecords, $enrolledSubjectIds, $passedSubjectIds, $passedSubjectCodes): array {
            $priorEnrollment = $enrolledSubjectRecords->firstWhere('subject_id', $subject->id);
            $isEnrolledOrTaken = in_array($subject->id, $enrolledSubjectIds, true);

            $prerequisites = is_array($subject->pre_riquisite) ? $subject->pre_riquisite : [];
            $unmetPrerequisites = [];

            foreach ($prerequisites as $prereq) {
                $prereqCode = mb_strtoupper(mb_trim((string) $prereq));
                if ($prereqCode === '') {
                    continue;
                }
                $isPrereqMet = in_array($prereqCode, $passedSubjectCodes, true)
                    || in_array((int) $prereq, $passedSubjectIds, true);

                if (! $isPrereqMet) {
                    $unmetPrerequisites[] = $prereq;
                }
            }

            $status = 'available';
            if ($isEnrolledOrTaken) {
                $status = ($priorEnrollment && $priorEnrollment->grade !== null) ? 'completed' : 'currently_enrolled';
            } elseif (! empty($unmetPrerequisites)) {
                $status = 'prerequisites_unfulfilled';
            }

            return [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
                'academic_year' => $subject->academic_year,
                'semester' => $subject->semester,
                'prerequisites' => $prerequisites,
                'unmet_prerequisites' => $unmetPrerequisites,
                'status' => $status,
                'last_grade' => $priorEnrollment?->grade,
                'last_remarks' => $priorEnrollment?->remarks,
            ];
        })->values()->all();

        return Response::structured([
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'course_id' => $student->course_id,
                'academic_year' => $student->academic_year,
            ],
            'term' => [
                'year_level' => $yearLevel,
                'semester' => $semester,
            ],
            'subjects_count' => count($curriculum),
            'available_count' => count(array_filter($curriculum, fn ($s) => $s['status'] === 'available')),
            'prerequisites_unfulfilled_count' => count(array_filter($curriculum, fn ($s) => $s['status'] === 'prerequisites_unfulfilled')),
            'subjects' => $curriculum,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->min(1)->description('The student database ID or student number. Optional for student callers.'),
            'year_level' => $schema->integer()->min(1)->max(6)->description('Year level to filter (e.g. 1, 2, 3, 4). Defaults to student\'s current academic year.'),
            'semester' => $schema->integer()->enum([1, 2])->description('Semester to filter (1 or 2). Defaults to current semester.'),
        ];
    }
}
