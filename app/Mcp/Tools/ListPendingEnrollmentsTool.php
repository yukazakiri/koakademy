<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\EnrollmentRequirement;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List enrollments in the active school awaiting administrative review, departmental verification, or cashier approval.')]
#[IsReadOnly]
final class ListPendingEnrollmentsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?GeneralSettingsService $settings = null,
        private ?EnrollmentPipelineService $pipeline = null,
    ) {
        $this->settings ??= app(GeneralSettingsService::class);
        $this->pipeline ??= app(EnrollmentPipelineService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireAdmin($request);
        $this->requirePermission($user, 'ViewAny:StudentEnrollment', 'You are not permitted to view enrollment lists.');

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'course_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $validated['status'] ?? null;
        $courseId = isset($validated['course_id']) ? (int) $validated['course_id'] : null;
        $limit = (int) ($validated['limit'] ?? 20);

        $schoolYear = $this->settings->getCurrentSchoolYearString();
        $semester = $this->settings->getCurrentSemester();

        $query = StudentEnrollment::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->with([
                'student:id,student_id,first_name,middle_name,last_name,suffix',
                'course:id,code,title',
            ])
            ->withCount([
                'requirements as pending_requirements_count' => fn ($q) => $q->where('status', EnrollmentRequirement::Pending),
            ]);

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        } else {
            $query->whereNotIn('status', [
                $this->pipeline->getCashierVerifiedStatus(),
                'enrolled',
                'completed',
                'rejected',
                'cancelled',
                ...$this->pipeline->getEnrolledStatuses(),
            ]);
        }

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        $enrollments = $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (StudentEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'workflow_runtime' => $enrollment->workflow_runtime,
                'current_step_key' => $enrollment->current_step_key,
                'submission_channel' => $enrollment->submission_channel,
                'pending_requirements_count' => $enrollment->pending_requirements_count,
                'created_at' => $enrollment->created_at?->toIso8601String(),
                'student' => $enrollment->student === null ? null : [
                    'id' => $enrollment->student->id,
                    'student_number' => (string) $enrollment->student->student_id,
                    'name' => $enrollment->student->full_name,
                ],
                'course' => $enrollment->course === null ? null : [
                    'id' => $enrollment->course->id,
                    'code' => $enrollment->course->code,
                    'title' => $enrollment->course->title,
                ],
            ])
            ->values()
            ->all();

        return Response::structured([
            'school_year' => $schoolYear,
            'semester' => $semester,
            'count' => count($enrollments),
            'enrollments' => $enrollments,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Optional status filter, e.g. "pending", "submitted", "department_verification". Omit to list all awaiting review.'),
            'course_id' => $schema->integer()->min(1)->description('Optional course ID filter.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum records to return. Defaults to 20.'),
        ];
    }
}
