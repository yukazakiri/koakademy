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
        ->and($data['download_url'])->toContain('/ai/download-document/');

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

    $generalDownloadRes = $this->actingAs($admin)->get("/ai/download-document/{$docJson['document_id']}");
    $generalDownloadRes->assertOk();
    expect($generalDownloadRes->headers->get('content-type'))->toContain('text/csv');

    // 4. On-the-fly export endpoint
    $exportRes = $this->actingAs($admin)->post('/administrators/ai/export-document', [
        'title' => 'Live Memo',
        'format' => 'markdown',
        'content' => '# Official Memo',
    ]);
    $exportRes->assertOk();
    expect($exportRes->headers->get('content-type'))->toContain('text/markdown');
});

it('processes uploaded images, spreadsheets, and documents for AI consumption', function (): void {
    $processor = app(App\Services\Ai\AiAttachmentProcessor::class);

    // 1. Uploaded CSV spreadsheet
    $csvFile = Illuminate\Http\UploadedFile::fake()->createWithContent('grades.csv', "Student,Score\nJuan,95\nMaria,98");
    $resultCsv = $processor->process([$csvFile], 'Analyze this grade list');

    expect($resultCsv['enrichedPrompt'])->toContain('Juan')
        ->and($resultCsv['enrichedPrompt'])->toContain('Score')
        ->and($resultCsv['attachments'])->toHaveCount(1);

    // 2. Uploaded image
    $imageFile = Illuminate\Http\UploadedFile::fake()->image('campus_map.png', 400, 300);
    $resultImg = $processor->process([$imageFile], 'What is in this image?');

    expect($resultImg['attachments'])->toHaveCount(1)
        ->and($resultImg['attachments'][0])->toBeInstanceOf(Laravel\Ai\Files\Image::class);
});

it('accepts file attachments on the administrative ai chat endpoint', function (): void {
    App\Ai\Agents\AdminExecutiveAgent::fake([
        'I have parsed the attached spreadsheet and generated your report.',
    ]);

    $admin = User::factory()->create(['role' => App\Enums\UserRole::SuperAdmin]);
    $sheetFile = Illuminate\Http\UploadedFile::fake()->createWithContent('enrollment.csv', "Department,Count\nCCS,400\nCBA,300");

    $response = $this->actingAs($admin)->post('/administrators/ai/chat', [
        'agent' => 'admin_executive',
        'message' => 'Analyze the attached enrollment numbers',
        'attachments' => [$sheetFile],
    ]);

    $response->assertOk();
});

it('accepts custom model selection and streams response with diagnostics', function (): void {
    App\Ai\Agents\AdminExecutiveAgent::fake([
        'Responding with custom model.',
    ]);

    $admin = User::factory()->create(['role' => App\Enums\UserRole::SuperAdmin]);

    $response = $this->actingAs($admin)->post('/administrators/ai/chat', [
        'agent' => 'admin_executive',
        'message' => 'Hello from custom model',
        'model' => 'vllm-cluster:meta-llama/Llama-3.3-70B-Instruct',
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/event-stream');
});

it('surfaces error diagnostics with provider context when AI streaming fails', function (): void {
    $admin = User::factory()->create(['role' => App\Enums\UserRole::SuperAdmin]);

    // Force an unconfigured/invalid provider to test error reporting
    config(['ai.default' => 'unreachable_custom_provider']);
    config(['ai.providers.unreachable_custom_provider' => [
        'driver' => 'openai-compatible',
        'url' => 'http://127.0.0.1:9999/v1',
        'key' => 'bad-key',
        'models' => ['text' => ['default' => 'invalid-model']],
    ]]);

    $response = $this->actingAs($admin)->post('/administrators/ai/chat', [
        'agent' => 'admin_executive',
        'message' => 'Test error handling',
        'provider' => 'unreachable_custom_provider',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('"type":"error"')
        ->and($content)->toContain('unreachable_custom_provider');
});

it('uses a provider-specific default before attempting a configured AI fallback provider', function (): void {
    $controller = new App\Http\Controllers\AiChatController;
    $method = new ReflectionMethod($controller, 'resolveFallbackTarget');

    $result = $method->invoke($controller, [
        'primary_provider' => 'anthropic',
        'fallback_provider' => 'openai',
        'failover_enabled' => true,
        'providers' => [
            'anthropic' => ['default_chat_model' => 'claude-3-7-sonnet'],
            'openai' => ['default_chat_model' => 'gpt-4o-mini'],
        ],
        'custom_providers' => [],
    ], 'anthropic', 'claude-opus-unavailable');

    expect($result)->toBe(['anthropic', 'claude-3-7-sonnet']);
});

it('uses the configured failover provider when the current provider default also failed', function (): void {
    $controller = new App\Http\Controllers\AdministratorAiController;
    $method = new ReflectionMethod($controller, 'resolveFallbackTarget');

    $result = $method->invoke($controller, [
        'primary_provider' => 'anthropic',
        'fallback_provider' => 'openai',
        'failover_enabled' => true,
        'providers' => [
            'anthropic' => ['default_chat_model' => 'claude-3-7-sonnet'],
            'openai' => ['default_chat_model' => 'gpt-4o-mini'],
        ],
        'custom_providers' => [],
    ], 'anthropic', 'claude-3-7-sonnet');

    expect($result)->toBe(['openai', 'gpt-4o-mini']);
});

it('filters out unconfigured providers from model options in analyticsSummary', function (): void {
    $service = app(App\Services\Ai\AiSettingsService::class);

    // Save with OpenAI configured (has key), and custom vllm configured, but Anthropic has no key
    $service->merge([
        'enabled' => true,
        'primary_provider' => 'anthropic',
        'providers' => [
            'anthropic' => ['enabled' => true, 'api_key' => ''],
            'openai' => ['enabled' => true, 'api_key' => 'sk-valid-key', 'default_chat_model' => 'gpt-4o'],
        ],
        'custom_providers' => [
            'campus_vllm' => [
                'key' => 'campus_vllm',
                'label' => 'Campus GPU Cluster',
                'enabled' => true,
                'base_url' => 'http://192.168.1.50:8000/v1',
                'default_chat_model' => 'llama-3.3-70b',
            ],
            'disabled_custom' => [
                'key' => 'disabled_custom',
                'label' => 'Disabled Cluster',
                'enabled' => false,
                'base_url' => 'http://192.168.1.60:8000/v1',
                'default_chat_model' => 'llama-old',
            ],
        ],
    ]);

    $admin = User::factory()->create(['role' => App\Enums\UserRole::SuperAdmin]);
    $response = $this->actingAs($admin)->getJson('/administrators/ai/analytics-summary');

    $response->assertOk();
    $data = $response->json();

    $models = $data['models'];
    $modelIds = array_column($models, 'id');
    $providers = array_unique(array_column($models, 'provider'));

    // Anthropic should NOT be in models because it has no key!
    expect($providers)->not->toContain('anthropic')
        // Disabled custom provider should NOT be in models!
        ->and($providers)->not->toContain('disabled_custom')
        // Configured providers should be present and grouped!
        ->and($modelIds)->toContain('openai:gpt-4o')
        ->and($modelIds)->toContain('campus_vllm:llama-3.3-70b')
        ->and($models[0])->toHaveKey('provider_name');
});

it('automatically falls back to configured custom provider on general ai chat endpoint', function (): void {
    StudentAdvisorAgent::fake([
        'Responding from custom provider fallback.',
    ]);

    $service = app(App\Services\Ai\AiSettingsService::class);
    $service->merge([
        'enabled' => true,
        'primary_provider' => 'anthropic',
        'providers' => [
            'anthropic' => ['enabled' => true, 'api_key' => ''],
        ],
        'custom_providers' => [
            'local_vllm' => [
                'key' => 'local_vllm',
                'label' => 'Local vLLM',
                'enabled' => true,
                'base_url' => 'http://localhost:8000/v1',
                'default_chat_model' => 'qwen-2.5',
            ],
        ],
    ]);

    $student = User::factory()->create(['role' => App\Enums\UserRole::Student]);

    $response = $this->actingAs($student)->post('/ai/chat', [
        'agent' => 'student_advisor',
        'message' => 'Hello advisor',
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/event-stream');
});

it('retrieves enrolled students in a class via GetClassEnrollmentsTool', function (): void {
    $class = Classes::factory()->create([
        'subject_code' => 'CS101',
        'section' => '1A',
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);

    $student = Student::factory()->create([
        'first_name' => 'Maria',
        'last_name' => 'Clara',
        'student_id' => 2026123,
    ]);

    App\Models\ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'enrolled',
        'school_id' => $class->school_id,
    ]);

    $tool = new App\Ai\Tools\GetClassEnrollmentsTool;
    $result = $tool->handle(new Request(['subject_code' => 'CS101', 'section' => '1A']));
    $data = json_decode((string) $result, true);

    expect($data)->toHaveKey('students')
        ->and($data['students'])->toHaveCount(1)
        ->and($data['students'][0]['name'])->toBe('Maria Clara')
        ->and((int) $data['students'][0]['student_number'])->toBe(2026123)
        ->and($data['enrolled_count'])->toBe(1);
});

it('searches class schedules via LookupClassSchedulesTool', function (): void {
    Classes::factory()->create([
        'subject_code' => 'MATH101',
        'section' => 'SEC-M',
    ]);

    $tool = new App\Ai\Tools\LookupClassSchedulesTool;
    $result = $tool->handle(new Request(['subject_code' => 'MATH101']));
    $data = json_decode((string) $result, true);

    expect($data)->toHaveKey('classes')
        ->and($data['count'])->toBeGreaterThanOrEqual(1)
        ->and($data['classes'][0]['subject_code'])->toContain('MATH101');
});

it('searches student directory via SearchStudentsTool', function (): void {
    $student = Student::factory()->create([
        'first_name' => 'Crisostomo',
        'last_name' => 'Ibarra',
        'student_id' => 2026999,
    ]);

    $tool = new App\Ai\Tools\SearchStudentsTool;
    $result = $tool->handle(new Request(['query' => 'Crisostomo']));
    $data = json_decode((string) $result, true);

    expect($data)->toHaveKey('students')
        ->and($data['count'])->toBeGreaterThanOrEqual(1)
        ->and($data['students'][0]['name'])->toBe('Crisostomo Ibarra')
        ->and((int) $data['students'][0]['student_id'])->toBe(2026999);
});

it('retrieves class grades sheet via GetClassGradesTool', function (): void {
    $class = Classes::factory()->create([
        'subject_code' => 'CS102',
        'section' => '1B',
    ]);

    $student = Student::factory()->create([
        'first_name' => 'Basilio',
        'last_name' => 'Alvarez',
        'student_id' => 2026456,
    ]);

    App\Models\ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'enrolled',
        'prelim_grade' => 1.5,
        'midterm_grade' => 1.75,
        'finals_grade' => 1.25,
        'remarks' => 'Passed',
        'school_id' => $class->school_id,
    ]);

    $tool = new App\Ai\Tools\GetClassGradesTool;
    $result = $tool->handle(new Request(['subject_code' => 'CS102', 'section' => '1B']));
    $data = json_decode((string) $result, true);

    expect($data)->toHaveKey('grade_sheet')
        ->and($data['total_students'])->toBe(1)
        ->and($data['passed_count'])->toBe(1)
        ->and($data['grade_sheet'][0]['name'])->toBe('Basilio Alvarez')
        ->and($data['grade_sheet'][0]['finals'])->toBe(1.25);
});

it('retrieves class attendance breakdown via GetClassAttendanceSummaryTool', function (): void {
    $class = Classes::factory()->create([
        'subject_code' => 'ENG101',
        'section' => 'SEC-A',
    ]);

    $student = Student::factory()->create([
        'first_name' => 'Isagani',
        'last_name' => 'Valenzuela',
        'student_id' => 2026789,
    ]);

    $enrollment = App\Models\ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'enrolled',
        'school_id' => $class->school_id,
    ]);

    $session = App\Models\ClassAttendanceSession::create([
        'class_id' => $class->id,
        'session_date' => now()->toDateString(),
        'is_no_meeting' => false,
    ]);

    App\Models\ClassAttendanceRecord::create([
        'class_attendance_session_id' => $session->id,
        'class_enrollment_id' => $enrollment->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => App\Enums\AttendanceStatus::Present,
    ]);

    $tool = new App\Ai\Tools\GetClassAttendanceSummaryTool;
    $result = $tool->handle(new Request(['subject_code' => 'ENG101', 'section' => 'SEC-A']));
    $data = json_decode((string) $result, true);

    expect($data)->toHaveKey('students')
        ->and($data['total_sessions_conducted'])->toBe(1)
        ->and($data['students'][0]['present'])->toBe(1)
        ->and($data['students'][0]['name'])->toBe('Isagani Valenzuela');
});

it('retrieves faculty teaching load via GetFacultyAssignedClassesTool', function (): void {
    $faculty = App\Models\Faculty::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Luna',
    ]);

    Classes::factory()->create([
        'faculty_id' => $faculty->id,
        'subject_code' => 'ART101',
        'section' => '1C',
    ]);

    $tool = new App\Ai\Tools\GetFacultyAssignedClassesTool;
    $result = $tool->handle(new Request(['faculty_name' => 'Luna']));
    $data = json_decode((string) $result, true);

    expect($data)->toHaveKey('assigned_classes')
        ->and($data['total_classes'])->toBe(1)
        ->and($data['faculty_name'])->toContain('Luna')
        ->and($data['assigned_classes'][0]['subject_code'])->toBe('ART101');
});

it('checks classroom availability via LookupRoomAvailabilityTool', function (): void {
    $room = App\Models\Room::factory()->create([
        'name' => 'Room 302',
    ]);

    $tool = new App\Ai\Tools\LookupRoomAvailabilityTool;
    $result = $tool->handle(new Request(['room_name' => 'Room 302']));
    $data = json_decode((string) $result, true);

    expect($data)->toHaveKey('rooms')
        ->and($data['count'])->toBeGreaterThanOrEqual(1)
        ->and($data['rooms'][0]['name'])->toBe('Room 302');
});
