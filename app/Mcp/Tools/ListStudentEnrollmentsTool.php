<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\StudentEnrollment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List all semester enrollment records for a student across their academic history.')]
#[IsReadOnly]
final class ListStudentEnrollmentsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $studentId = $request->get('student_id') !== null ? (int) $request->get('student_id') : null;
        $student = $this->resolveStudentForCaller($user, $studentId);

        if (! $user->isStudentRole()) {
            $this->requirePermission($user, 'View:StudentEnrollment', 'You are not permitted to view student enrollment history.');
        }

        $limit = min(max((int) ($request->get('limit') ?? 20), 1), 50);

        $enrollments = StudentEnrollment::query()
            ->where('student_id', (string) $student->id)
            ->with(['course:id,code,title'])
            ->withCount('subjectsEnrolled')
            ->orderBy('school_year', 'desc')
            ->orderBy('semester', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (StudentEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'academic_year' => $enrollment->academic_year,
                'status' => $enrollment->status,
                'workflow_runtime' => $enrollment->workflow_runtime,
                'current_step_key' => $enrollment->current_step_key,
                'terminal_outcome' => $enrollment->terminal_outcome,
                'downpayment' => (float) $enrollment->downpayment,
                'remarks' => $enrollment->remarks,
                'submission_channel' => $enrollment->submission_channel,
                'subjects_enrolled_count' => $enrollment->subjects_enrolled_count,
                'created_at' => $enrollment->created_at?->toIso8601String(),
                'course' => $enrollment->course === null ? null : [
                    'id' => $enrollment->course->id,
                    'code' => $enrollment->course->code,
                    'title' => $enrollment->course->title,
                ],
            ])
            ->values()
            ->all();

        return Response::structured([
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
            ],
            'count' => count($enrollments),
            'enrollments' => $enrollments,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->min(1)->description('The student database ID or student number. Optional for student callers.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum number of enrollment records to return. Defaults to 20.'),
        ];
    }
}
