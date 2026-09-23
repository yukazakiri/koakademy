<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AdvanceEnrollmentStepTool;
use App\Mcp\Tools\DropStudentSubjectEnrollmentTool;
use App\Mcp\Tools\EnrollStudentSubjectTool;
use App\Mcp\Tools\GetAvailableSubjectsTool;
use App\Mcp\Tools\GetCourseCurriculumTool;
use App\Mcp\Tools\GetEnrollmentAuditTrailTool;
use App\Mcp\Tools\GetEnrollmentStatusTool;
use App\Mcp\Tools\GetMyContextTool;
use App\Mcp\Tools\GetSchoolDetailsTool;
use App\Mcp\Tools\GetSchoolMetricsTool;
use App\Mcp\Tools\GetStatementOfAccountTool;
use App\Mcp\Tools\GetStudentProfileTool;
use App\Mcp\Tools\GetStudentScheduleTool;
use App\Mcp\Tools\GetStudentSubjectEnrollmentsTool;
use App\Mcp\Tools\ListAcademicOfferingsTool;
use App\Mcp\Tools\ListPendingEnrollmentsTool;
use App\Mcp\Tools\ListStudentEnrollmentsTool;
use App\Mcp\Tools\SearchFacultyTool;
use App\Mcp\Tools\SearchStudentsTool;
use App\Mcp\Tools\UpdateEnrollmentRemarksTool;
use App\Mcp\Tools\UpdateSubjectEnrollmentGradeTool;
use App\Mcp\Tools\VerifyEnrollmentRequirementTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('KoAkademy')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    KoAkademy exposes authorized school administration data to internal AI agents.

    Every tool operates only within the school selected for the authenticated API key. Use get_my_context before other tools to understand the caller and selected school. Search results and IDs are school-scoped. Read tools require an MCP read key and the caller's normal application permissions. Mutations require an MCP write key, the caller's normal application permissions, and an explicit idempotency key.

    Do not infer or invent student, enrollment, financial, or requirement data. When a tool returns an access error, explain that the connected staff account lacks the required access rather than attempting another school's data.
    MARKDOWN)]
final class KoAkademyServer extends Server
{
    /** @var array<int, class-string<Server\Tool>> */
    protected array $tools = [
        GetMyContextTool::class,
        GetSchoolDetailsTool::class,
        GetSchoolMetricsTool::class,
        SearchStudentsTool::class,
        GetStudentProfileTool::class,
        GetStudentScheduleTool::class,
        SearchFacultyTool::class,
        ListStudentEnrollmentsTool::class,
        GetEnrollmentStatusTool::class,
        ListPendingEnrollmentsTool::class,
        GetEnrollmentAuditTrailTool::class,
        GetCourseCurriculumTool::class,
        GetAvailableSubjectsTool::class,
        GetStudentSubjectEnrollmentsTool::class,
        ListAcademicOfferingsTool::class,
        GetStatementOfAccountTool::class,
        AdvanceEnrollmentStepTool::class,
        VerifyEnrollmentRequirementTool::class,
        UpdateEnrollmentRemarksTool::class,
        EnrollStudentSubjectTool::class,
        UpdateSubjectEnrollmentGradeTool::class,
        DropStudentSubjectEnrollmentTool::class,
        \App\Mcp\Tools\QueryTimetableScheduleTool::class,
        \App\Mcp\Tools\ManageStudentTool::class,
        \App\Mcp\Tools\ManageCurriculumSubjectTool::class,
        \App\Mcp\Tools\ManageClassScheduleTool::class,
        \App\Mcp\Tools\ManageRoomTool::class,
    ];
}
