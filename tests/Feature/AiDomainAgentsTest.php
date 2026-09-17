<?php

declare(strict_types=1);

use App\Ai\Agents\BursarFinanceAgent;
use App\Ai\Agents\FacultyCopilotAgent;
use App\Ai\Agents\RegistrarAuditAgent;
use App\Ai\Agents\StudentAdvisorAgent;
use App\Ai\Tools\ApplyTuitionAdjustmentBatchTool;
use App\Ai\Tools\AuditStudentProfileImportTool;
use App\Ai\Tools\BatchUpdateClearanceTool;
use App\Ai\Tools\CampusKnowledgeSearchTool;
use App\Ai\Tools\CommitSubmissionGradeTool;
use App\Ai\Tools\CreateHelpTicketTool;
use App\Ai\Tools\DetectAtRiskStudentsTool;
use App\Ai\Tools\DraftEnrollmentCartTool;
use App\Ai\Tools\DraftInterventionNoticeTool;
use App\Ai\Tools\EscalateToDepartmentTool;
use App\Ai\Tools\ExplainStatementOfAccountTool;
use App\Ai\Tools\GenerateRubricTool;
use App\Ai\Tools\GetCurriculumProgressTool;
use App\Ai\Tools\SimulateScholarshipAdjustmentTool;
use App\Ai\Tools\SubmitEnrollmentPlanTool;
use App\Ai\Tools\ValidateAdjustmentSpreadsheetTool;
use App\Models\Classes;
use App\Models\HelpTicket;
use App\Models\Student;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    $this->withoutVite();
    Laravel\Pennant\Feature::flushCache();
    Laravel\Pennant\Feature::activateForEveryone(App\Features\Toggles\AiStudentAdvisor::class);
    Laravel\Pennant\Feature::activateForEveryone(App\Features\Toggles\AiFacultyAssistant::class);
    Laravel\Pennant\Feature::activateForEveryone(App\Features\Toggles\AiRegistrarAuditor::class);
    Laravel\Pennant\Feature::activateForEveryone(App\Features\Toggles\AiFinanceAssistant::class);
    Laravel\Pennant\Feature::activateForEveryone(App\Features\Toggles\AiHelpDeskAssistant::class);
});

it('audits student profile import spreadsheet and flags invalid LRNs', function (): void {
    $tool = new AuditStudentProfileImportTool;
    $result = $tool->handle(new Request([
        'profiles' => [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'lrn' => '123456789012',
                'email' => 'juan@example.com',
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'lrn' => 'invalid-lrn-too-short',
                'email' => 'maria@example.com',
            ],
        ],
    ]));

    $data = json_decode((string) $result, true);

    expect($data['total_rows_audited'])->toBe(2)
        ->and($data['anomaly_count'])->toBe(1)
        ->and($data['is_ready_for_import'])->toBeFalse()
        ->and($data['anomalies'][0]['issue'])->toContain('12 numeric digits');
});

it('calculates degree progress and remaining units for student advisor', function (): void {
    $student = Student::factory()->create();

    $tool = new GetCurriculumProgressTool;
    $result = $tool->handle(new Request([
        'student_id' => $student->id,
    ]));

    $data = json_decode((string) $result, true);

    expect($data['student_id'])->toBe($student->id)
        ->and($data['progress_percentage'])->toBeGreaterThan(0)
        ->and($data['total_units_required'])->toBe(142);
});

it('stages enrollment courses in student cart prior to submission', function (): void {
    $student = Student::factory()->create();
    $classA = Classes::factory()->create();
    $classB = Classes::factory()->create();

    $tool = new DraftEnrollmentCartTool;
    $result = $tool->handle(new Request([
        'student_id' => $student->id,
        'class_ids' => [$classA->id, $classB->id],
    ]));

    $data = json_decode((string) $result, true);

    expect($data['status'])->toBe('staged_in_cart')
        ->and($data['classes_count'])->toBe(2)
        ->and($data['total_units'])->toBe(6);
});

it('requires human approval before submitting an official enrollment plan', function (): void {
    $tool = new SubmitEnrollmentPlanTool;
    $approval = $tool->shouldRequestApproval(new Request([
        'student_id' => 1,
        'class_ids' => [101, 102],
    ]));

    expect($approval)->not->toBeNull()
        ->and($approval->reason)->toContain('2 courses');
});

it('generates multi-criteria rubric for faculty assignments', function (): void {
    $tool = new GenerateRubricTool;
    $result = $tool->handle(new Request([
        'subject_title' => 'Software Engineering 1',
        'assignment_title' => 'System Architecture Document',
        'max_points' => 100,
    ]));

    $data = json_decode((string) $result, true);

    expect($data['max_points'])->toBe(100)
        ->and($data['criteria'])->toHaveCount(3)
        ->and($data['criteria'][0]['dimension'])->toBe('Content Accuracy & Mastery');
});

it('requires human approval before committing grades to student submissions', function (): void {
    $tool = new CommitSubmissionGradeTool;
    $approval = $tool->shouldRequestApproval(new Request([
        'submission_id' => 5,
        'points' => 95,
        'feedback' => 'Great work',
    ]));

    expect($approval)->not->toBeNull()
        ->and($approval->reason)->toContain('95 points');
});

it('detects at-risk students based on missing submissions', function (): void {
    $class = Classes::factory()->create();
    $student = Student::factory()->create();
    $class->class_enrollments()->create([
        'student_id' => $student->id,
    ]);

    $tool = new DetectAtRiskStudentsTool;
    $result = $tool->handle(new Request([
        'class_id' => $class->id,
    ]));

    $data = json_decode((string) $result, true);

    expect($data['class_id'])->toBe($class->id)
        ->and($data['total_students'])->toBe(1);
});

it('drafts compassionate intervention notice for at-risk student', function (): void {
    $student = Student::factory()->create([
        'first_name' => 'Carlo',
        'last_name' => 'Aquino',
    ]);

    $tool = new DraftInterventionNoticeTool;
    $result = $tool->handle(new Request([
        'student_id' => $student->id,
        'course_title' => 'Data Structures and Algorithms',
        'reason' => 'three consecutive missed quizzes',
    ]));

    $data = json_decode((string) $result, true);

    expect($data['recipient'])->toBe('Carlo Aquino')
        ->and($data['body'])->toContain('three consecutive missed quizzes')
        ->and($data['body'])->toContain('office hours');
});

it('requires human approval before batch clearing student holds in registrar', function (): void {
    $tool = new BatchUpdateClearanceTool;
    $approval = $tool->shouldRequestApproval(new Request([
        'clearance_ids' => [1, 2, 3],
    ]));

    expect($approval)->not->toBeNull()
        ->and($approval->reason)->toContain('3 clearance records');
});

it('explains student statement of account breakdown', function (): void {
    $student = Student::factory()->create();

    $tool = new ExplainStatementOfAccountTool;
    $result = $tool->handle(new Request([
        'student_id' => $student->id,
    ]));

    $data = json_decode((string) $result, true);

    expect($data['student_id'])->toBe($student->id)
        ->and($data['assessment_breakdown'])->toHaveKey('tuition_units_amount')
        ->and($data['payment_summary']['remaining_balance'])->toBeGreaterThan(0);
});

it('validates tuition adjustment spreadsheet and catches duplicate rows', function (): void {
    $tool = new ValidateAdjustmentSpreadsheetTool;
    $result = $tool->handle(new Request([
        'rows' => [
            ['student_id' => 10, 'amount' => 1500, 'type' => 'discount', 'reason' => 'Sibling discount'],
            ['student_id' => 10, 'amount' => 1000, 'type' => 'discount', 'reason' => 'Duplicate row'],
        ],
    ]));

    $data = json_decode((string) $result, true);

    expect($data['passed'])->toBeFalse()
        ->and($data['anomaly_count'])->toBe(1)
        ->and($data['anomalies'][0]['issue'])->toContain('duplicate');
});

it('simulates scholarship discount rates accurately', function (): void {
    $tool = new SimulateScholarshipAdjustmentTool;
    $result = $tool->handle(new Request([
        'scholarship_type' => 'full',
        'base_tuition' => 20000,
    ]));

    $data = json_decode((string) $result, true);

    expect($data['discount_percentage'])->toBe(100)
        ->and((float) $data['net_tuition_due'])->toEqual(0.0);
});

it('requires human approval before applying financial adjustments to ledgers', function (): void {
    $tool = new ApplyTuitionAdjustmentBatchTool;
    $approval = $tool->shouldRequestApproval(new Request([
        'adjustments' => [
            ['student_id' => 1, 'amount' => 500, 'reason' => 'Late refund'],
        ],
        'justification' => 'Authorized by Bursar',
    ]));

    expect($approval)->not->toBeNull()
        ->and($approval->reason)->toContain('financial adjustments');
});

it('searches campus knowledge base for policies', function (): void {
    $tool = new CampusKnowledgeSearchTool;
    $result = $tool->handle(new Request([
        'query' => 'What is the attendance and clearance policy?',
    ]));

    expect((string) $result)->toContain('Attendance Policy')
        ->and((string) $result)->toContain('Clearance Policy');
});

it('creates official support tickets when needed', function (): void {
    $user = User::factory()->create();

    $tool = new CreateHelpTicketTool;
    $result = $tool->handle(new Request([
        'user_id' => $user->id,
        'subject' => 'Unable to access LMS quiz',
        'type' => 'technical_issue',
        'message' => 'The quiz times out when loading from home network.',
        'priority' => 'high',
    ]));

    $data = json_decode((string) $result, true);

    expect($data['status'])->toBe('open')
        ->and(HelpTicket::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('escalates complex inquiries to the proper department desk', function (): void {
    $user = User::factory()->create();

    $tool = new EscalateToDepartmentTool;
    $result = $tool->handle(new Request([
        'user_id' => $user->id,
        'target_department' => 'Registrar',
        'summary_of_inquiry' => 'Transfer credentials from previous university need manual evaluation.',
        'urgency' => 'high',
    ]));

    $data = json_decode((string) $result, true);

    expect($data['status'])->toBe('escalated_to_staff')
        ->and($data['target_department'])->toBe('Registrar');
});

it('supports sub-agent tool invocation on FacultyCopilotAgent', function (): void {
    FacultyCopilotAgent::fake([
        'I have analyzed the class attendance and generated the requested rubric.',
    ]);

    $agent = new FacultyCopilotAgent;
    $response = $agent->prompt('Analyze student risk in my section and generate a rubric.');

    expect((string) $response)->toContain('generated the requested rubric');
    FacultyCopilotAgent::assertPrompted('Analyze student risk in my section and generate a rubric.');
});

it('supports RegistrarAuditAgent and BursarFinanceAgent in AiChatController with role enforcement', function (): void {
    RegistrarAuditAgent::fake(['All graduation holds have been audited.']);
    BursarFinanceAgent::fake(['The tuition breakdown has been computed.']);

    $admin = User::factory()->create(['role' => App\Enums\UserRole::SuperAdmin]);
    $student = User::factory()->create(['role' => App\Enums\UserRole::Student]);

    // Admin can access Registrar Audit Agent route
    $responseA = $this->actingAs($admin)->postJson('/ai/chat', [
        'agent' => 'registrar_auditor',
        'message' => 'Check graduation clearance for student 1',
    ]);
    $responseA->assertOk();

    // Student is forbidden from accessing Registrar Audit Agent
    $forbiddenResponse = $this->actingAs($student)->postJson('/ai/chat', [
        'agent' => 'registrar_auditor',
        'message' => 'Check graduation clearance for student 1',
    ]);
    $forbiddenResponse->assertForbidden();

    // Student can access Bursar Finance Agent route
    $responseB = $this->actingAs($student)->postJson('/ai/chat', [
        'agent' => 'bursar_finance',
        'message' => 'Explain my tuition fees',
    ]);
    $responseB->assertOk();
});

it('sanitizes credit cards and SSNs via SanitizePromptMiddleware', function (): void {
    $middleware = new App\Ai\Middleware\SanitizePromptMiddleware;
    $agent = new StudentAdvisorAgent;
    $provider = Mockery::mock(Laravel\Ai\Contracts\Providers\TextProvider::class);

    $prompt = new Laravel\Ai\Prompts\AgentPrompt(
        $agent,
        'My card is 4111 2222 3333 4444 and ssn is 123-45-6789',
        [],
        $provider,
        'test-model'
    );

    $middleware->handle($prompt, function ($nextPrompt) {
        expect($nextPrompt->prompt)->toContain('[REDACTED_PAYMENT_CARD]')
            ->and($nextPrompt->prompt)->toContain('[REDACTED_IDENTIFIER]')
            ->and($nextPrompt->prompt)->not->toContain('4111 2222 3333 4444')
            ->and($nextPrompt->prompt)->not->toContain('123-45-6789');

        return new class
        {
            public function then(Closure $cb)
            {
                return $this;
            }
        };
    });
});

it('renders AI assistant overview stats widget in Filament dashboard', function (): void {
    $widget = new App\Filament\Widgets\AiAssistantOverviewWidget;
    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');
    $method->setAccessible(true);

    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getLabel())->toBe('Primary AI Provider')
        ->and($stats[1]->getLabel())->toBe('Default Agent Model')
        ->and($stats[2]->getLabel())->toBe('Failover Protection');
});

it('registers morph map for user, student, and faculty models in AppServiceProvider', function (): void {
    $morphMap = Illuminate\Database\Eloquent\Relations\Relation::morphMap();

    expect($morphMap)->toHaveKey('user')
        ->and($morphMap['user'])->toBe(User::class)
        ->and($morphMap)->toHaveKey('student')
        ->and($morphMap)->toHaveKey('faculty');
});

it('provides active provider summary on Filament AiAssistant page', function (): void {
    $page = new App\Filament\Pages\AiAssistant;
    $summary = $page->getActiveProviderSummary();

    expect($summary)->toHaveKey('provider')
        ->and($summary)->toHaveKey('chat_model')
        ->and($summary)->toHaveKey('failover');
});

it('queries institutional analytics metrics across categories', function (): void {
    $tool = new App\Ai\Tools\QueryCampusAnalyticsTool;

    $overview = $tool->handle(new Request(['category' => 'overview']));
    $overviewData = json_decode((string) $overview, true);

    expect($overviewData)->toHaveKey('headline_metrics')
        ->and($overviewData['headline_metrics'])->toHaveKey('total_student_population');

    $finance = $tool->handle(new Request(['category' => 'finance']));
    $financeData = json_decode((string) $finance, true);

    expect($financeData)->toHaveKey('collection_efficiency_percent')
        ->and($financeData)->toHaveKey('gross_assessed_tuition');
});

it('formats interactive visual analytics chart artifacts', function (): void {
    $tool = new App\Ai\Tools\GenerateAnalyticsChartTool;
    $result = $tool->handle(new Request([
        'chart_type' => 'bar',
        'title' => 'Enrollment by Department',
        'description' => 'Student distribution across academic colleges',
        'data' => [
            ['label' => 'Computer Studies', 'value' => 320],
            ['label' => 'Business', 'value' => 210],
        ],
        'metric_unit' => 'students',
    ]));

    $data = json_decode((string) $result, true);

    expect($data['_type'])->toBe('chart_artifact')
        ->and($data['chart_type'])->toBe('bar')
        ->and($data['data'])->toHaveCount(2)
        ->and($data['data'][0]['value'])->toBe(320);
});

it('generates downloadable administrative documents and stores them in cache', function (): void {
    $tool = new App\Ai\Tools\GenerateAdministrativeDocumentTool;
    $result = $tool->handle(new Request([
        'title' => 'Semester End Clearance Memo',
        'format' => 'pdf',
        'document_category' => 'clearance_memo',
        'content_markdown' => "# Official Circular\nAll students must complete clearance before enrollment.",
        'summary' => 'Directives on library and accounting hold settlements.',
    ]));

    $data = json_decode((string) $result, true);

    expect($data['_type'])->toBe('document_artifact')
        ->and($data['format'])->toBe('pdf')
        ->and($data)->toHaveKey('document_id')
        ->and($data['download_url'])->toContain('/administrators/ai/download-document/');

    // Assert it is stored in cache
    expect(Illuminate\Support\Facades\Cache::has("ai:doc:{$data['document_id']}"))->toBeTrue();
});

it('allows admin to stream chat, fetch KPI summaries, and download documents via AdministratorAiController', function (): void {
    App\Ai\Agents\AdminExecutiveAgent::fake([
        'Here is the executive report on campus operations.',
    ]);

    $admin = User::factory()->create(['role' => App\Enums\UserRole::SuperAdmin]);

    // 1. Chat endpoint
    $chatRes = $this->actingAs($admin)->postJson('/administrators/ai/chat', [
        'agent' => 'admin_executive',
        'message' => 'Summarize campus operations',
    ]);
    $chatRes->assertOk();

    // 2. Analytics KPI summary
    $summaryRes = $this->actingAs($admin)->getJson('/administrators/ai/analytics-summary');
    $summaryRes->assertOk()
        ->assertJsonStructure(['academic_period', 'kpis', 'quick_prompts']);

    // 3. Document download endpoint
    $tool = new App\Ai\Tools\GenerateAdministrativeDocumentTool;
    $docJson = json_decode((string) $tool->handle(new Request([
        'title' => 'Executive Summary',
        'format' => 'csv',
        'document_category' => 'enrollment_report',
        'content_markdown' => "Metric,Count\nEnrolled,450\nRetention,95%",
    ])), true);

    $downloadRes = $this->actingAs($admin)->get("/administrators/ai/download-document/{$docJson['document_id']}");
    $downloadRes->assertOk();
    expect($downloadRes->headers->get('content-type'))->toContain('text/csv');

    // 4. On-the-fly export endpoint
    $exportRes = $this->actingAs($admin)->post('/administrators/ai/export-document', [
        'title' => 'Live Memo',
        'format' => 'markdown',
        'content' => '# Official Memo',
    ]);
    $exportRes->assertOk();
    expect($exportRes->headers->get('content-type'))->toContain('text/markdown');
});
