<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\StudentStatus;
use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
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

#[Description('Get operational KPI metrics for the active school and current academic period (students count by status, active enrollments, faculty count, and active classes). Restricted to administrators.')]
#[IsReadOnly]
final class GetSchoolMetricsTool extends Tool
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
        $this->requireAdmin($request);
        $school = $this->school();
        $schoolYear = $this->settings->getCurrentSchoolYearString();
        $semester = $this->settings->getCurrentSemester();

        $totalStudents = Student::query()->count();
        $enrolledStudents = Student::query()->where('status', StudentStatus::Enrolled->value)->count();
        $applicants = Student::query()->where('status', StudentStatus::Applicant->value)->count();
        $graduated = Student::query()->where('status', StudentStatus::Graduated->value)->count();

        $enrolledThisPeriod = StudentEnrollment::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->whereIn('status', [
                $this->pipeline->getCashierVerifiedStatus(),
                'enrolled',
                'completed',
                ...$this->pipeline->getEnrolledStatuses(),
            ])
            ->count();

        $pendingEnrollments = StudentEnrollment::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->where('status', $this->pipeline->getPendingStatus())
            ->count();

        $activeClassesCount = Classes::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->count();

        $facultyCount = Faculty::query()->count();

        return Response::structured([
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
            ],
            'academic_period' => [
                'school_year' => $schoolYear,
                'semester' => $semester,
            ],
            'metrics' => [
                'total_students' => $totalStudents,
                'enrolled_students' => $enrolledStudents,
                'applicants' => $applicants,
                'graduated_students' => $graduated,
                'enrolled_this_period' => $enrolledThisPeriod,
                'pending_enrollments' => $pendingEnrollments,
                'active_classes' => $activeClassesCount,
                'total_faculty' => $facultyCount,
            ],
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
