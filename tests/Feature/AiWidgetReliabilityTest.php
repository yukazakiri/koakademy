<?php

declare(strict_types=1);

use App\Ai\Agents\AdminExecutiveAgent;
use App\Ai\Agents\RegistrarAuditAgent;
use App\Ai\Mcp\ExternalMcpToolResolver;
use App\Ai\Mcp\McpClientFactory;
use App\Ai\Mcp\ResilientMcpServerTool;
use App\Ai\Tools\AnalyzeTranscriptDocumentTool;
use App\Ai\Tools\ApplyTuitionAdjustmentBatchTool;
use App\Ai\Tools\ManageClassEnrollmentTool;
use App\Ai\Tools\ManageClassScheduleTool;
use App\Ai\Tools\ManageCurriculumSubjectTool;
use App\Ai\Tools\ManageEnrollmentTool;
use App\Ai\Tools\ManageRoomTool;
use App\Ai\Tools\ManageStudentTool;
use App\Ai\Tools\ManageSubjectEnrollmentTool;
use App\Ai\Tools\QueryTimetableScheduleTool;
use App\Ai\Tools\SimulatePolicyImpactTool;
use App\Enums\UserRole;
use App\Features\Toggles\AiAdminExecutive;
use App\Features\Toggles\AiFinanceAssistant;
use App\Features\Toggles\AiHelpDeskAssistant;
use App\Features\Toggles\AiRegistrarAuditor;
use App\Features\Toggles\McpExternalIntegrations;
use App\Models\AiChatRun;
use App\Models\Classes;
use App\Models\Course;
use App\Models\McpServer;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\User;
use App\Services\Ai\AiAttachmentProcessor;
use App\Services\Ai\AiSettingsService;
use App\Services\TenantContext;
use App\Services\TuitionAdjustmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Tools\Request as ToolRequest;
use Laravel\Pennant\Feature;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->withoutVite();
    app(AiSettingsService::class)->clearCache();

    Feature::flushCache();
    Feature::activateForEveryone(AiAdminExecutive::class);
    Feature::activateForEveryone(AiRegistrarAuditor::class);
    Feature::activateForEveryone(AiFinanceAssistant::class);
    Feature::activateForEveryone(AiHelpDeskAssistant::class);
    Feature::activateForEveryone(McpExternalIntegrations::class);
});

/**
 * Parse SSE data lines into decoded JSON event arrays.
 *
 * @return list<array<string, mixed>>
 */
function parseSseEvents(string $streamedContent): array
{
    $events = [];
    foreach (explode("\n", $streamedContent) as $line) {
        $trimmed = mb_trim($line);
        if (! str_starts_with($trimmed, 'data: ')) {
            continue;
        }
        $payload = mb_substr($trimmed, 6);
        if ($payload === '[DONE]') {
            continue;
        }
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            $events[] = $decoded;
        }
    }

    return $events;
}

it('forks a conversation branch on regenerate and resend without duplicating user turns or leaking later history', function (): void {
    $turnCounter = 0;
    AdminExecutiveAgent::fake(function (string $prompt) use (&$turnCounter): string {
        $turnCounter++;

        return match ($turnCounter) {
            1 => 'First turn answer.',
            2 => 'Generated Title',
            3 => 'Second turn answer.',
            default => 'Regenerated first turn answer.',
        };
    });

    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);

    // Turn 1
    $res1 = $this->actingAs($admin)->post(portalUrlForAdministrators('/administrators/ai/chat'), [
        'request_id' => (string) Str::uuid7(),
        'agent' => 'admin_executive',
        'message' => 'Summarize enrollment metrics.',
    ])->assertOk();

    $convEvent1 = collect(parseSseEvents($res1->streamedContent()))->firstWhere('type', 'conversation');
    expect($convEvent1)->not->toBeNull();
    $convId = $convEvent1['conversationId'];
    $firstUserMsgId = $convEvent1['userMessageId'];
    $firstAsstMsgId = $convEvent1['assistantMessageId'];

    // Turn 2 on the same conversation
    $res2 = $this->actingAs($admin)->post(portalUrlForAdministrators('/administrators/ai/chat'), [
        'request_id' => (string) Str::uuid7(),
        'conversation_id' => $convId,
        'agent' => 'admin_executive',
        'message' => 'Follow up on second year students.',
    ])->assertOk();

    $convEvent2 = collect(parseSseEvents($res2->streamedContent()))->firstWhere('type', 'conversation');
    expect($convEvent2['messages'])->toHaveCount(4);

    // Regenerate Turn 1's assistant response (an older turn)
    $res3 = $this->actingAs($admin)->post(portalUrlForAdministrators('/administrators/ai/chat'), [
        'request_id' => (string) Str::uuid7(),
        'conversation_id' => $convId,
        'replay_message_id' => $firstAsstMsgId,
        'agent' => 'admin_executive',
        'message' => 'Summarize enrollment metrics.',
    ])->assertOk();

    $convEvent3 = collect(parseSseEvents($res3->streamedContent()))->firstWhere('type', 'conversation');
    expect($convEvent3)->not->toBeNull();
    $branchConvId = $convEvent3['conversationId'];

    // The branched conversation has exactly 1 user message + 1 regenerated assistant message (no duplicates, no Turn 2 leak)
    expect($branchConvId)->not->toBe($convId)
        ->and($convEvent3['messages'])->toHaveCount(2)
        ->and($convEvent3['messages'][0]['role'])->toBe('user')
        ->and($convEvent3['messages'][0]['content'])->toBe('Summarize enrollment metrics.')
        ->and($convEvent3['messages'][1]['role'])->toBe('assistant')
        ->and($convEvent3['messages'][1]['content'])->toContain('Regenerated first turn answer.');

    // Original conversation remains intact with 4 messages
    expect(ConversationMessage::query()->where('conversation_id', $convId)->count())->toBe(4)
        ->and(ConversationMessage::query()->where('conversation_id', $branchConvId)->count())->toBe(2)
        ->and($firstUserMsgId)->not->toBe($convEvent3['userMessageId']);
});

it('blocks replaying a turn that is paused for approval or has already completed a record mutation', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);

    $conv = Conversation::query()->create([
        'id' => (string) Str::uuid7(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Mutation Turn',
    ]);

    $userMsg = ConversationMessage::query()->create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conv->id,
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'agent' => AdminExecutiveAgent::class,
        'role' => 'user',
        'content' => 'Create student record',
        'attachments' => [],
        'steps' => [],
        'status' => MessageStatus::Completed,
        'usage' => [],
        'meta' => [],
    ]);

    $pausedAsst = ConversationMessage::query()->create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conv->id,
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'agent' => AdminExecutiveAgent::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'steps' => [],
        'status' => MessageStatus::Paused,
        'usage' => [],
        'meta' => [],
    ]);

    $this->actingAs($admin)->postJson(portalUrlForAdministrators('/administrators/ai/chat'), [
        'conversation_id' => $conv->id,
        'replay_message_id' => $userMsg->id,
        'agent' => 'admin_executive',
    ])->assertStatus(409);

    // Mark as completed with a mutating tool call result
    $pausedAsst->update([
        'status' => MessageStatus::Completed,
        'steps' => [[
            'content' => 'Created student.',
            'tool_calls' => [[
                'id' => 'call_mut_1',
                'name' => 'ManageStudentTool',
                'arguments' => ['action' => 'create', 'first_name' => 'Ada'],
                'result' => json_encode(['success' => true]),
            ]],
        ]],
    ]);

    $this->actingAs($admin)->postJson(portalUrlForAdministrators('/administrators/ai/chat'), [
        'conversation_id' => $conv->id,
        'replay_message_id' => $pausedAsst->id,
        'agent' => 'admin_executive',
    ])->assertStatus(409);
});

it('deduplicates repeated request_id submissions without re-invoking the model and rejects payload mismatches', function (): void {
    AdminExecutiveAgent::fake([
        'Idempotent response.',
    ]);

    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $requestId = (string) Str::uuid7();

    $first = $this->actingAs($admin)->post(portalUrlForAdministrators('/administrators/ai/chat'), [
        'request_id' => $requestId,
        'agent' => 'admin_executive',
        'message' => 'Check headcount.',
    ])->assertOk();

    $firstEvents = parseSseEvents($first->streamedContent());
    $firstConv = collect($firstEvents)->firstWhere('type', 'conversation');

    // Second submission with the exact same request_id and payload streams the saved snapshot without a second model call
    $second = $this->actingAs($admin)->post(portalUrlForAdministrators('/administrators/ai/chat'), [
        'request_id' => $requestId,
        'agent' => 'admin_executive',
        'message' => 'Check headcount.',
    ])->assertOk();

    $secondConv = collect(parseSseEvents($second->streamedContent()))->firstWhere('type', 'conversation');
    expect($secondConv['conversationId'])->toBe($firstConv['conversationId'])
        ->and($secondConv['assistantMessageId'])->toBe($firstConv['assistantMessageId'])
        ->and(ConversationMessage::query()->where('conversation_id', $firstConv['conversationId'])->count())->toBe(2);

    // Reusing the same request_id for a different message fails validation
    $this->actingAs($admin)->postJson(portalUrlForAdministrators('/administrators/ai/chat'), [
        'request_id' => $requestId,
        'agent' => 'admin_executive',
        'message' => 'Different prompt altogether.',
    ])->assertUnprocessable();
});

it('restores originating run agent and model on approval continuation even if UI agent changed', function (): void {
    RegistrarAuditAgent::fake([
        'Graduation clearance approved and recorded.',
    ]);

    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);

    $conv = Conversation::query()->create([
        'id' => (string) Str::uuid7(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Registrar Approval',
    ]);

    AiChatRun::query()->create([
        'user_id' => $admin->id,
        'school_id' => $school->id,
        'request_id' => (string) Str::uuid7(),
        'conversation_id' => $conv->id,
        'agent' => 'registrar_auditor',
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'status' => 'paused',
        'options' => ['thinking' => true, 'search' => false, 'auto_approve' => false],
        'payload_hash' => hash('sha256', 'origin'),
    ]);

    $response = $this->actingAs($admin)->post(portalUrlForAdministrators('/administrators/ai/chat'), [
        'request_id' => (string) Str::uuid7(),
        'conversation_id' => $conv->id,
        // Caller sends a different agent tab, but continuationOptions restores registrar_auditor
        'agent' => 'bursar_finance',
        'decisions' => [
            'call_reg_1' => ['action' => 'approve'],
        ],
    ])->assertOk();

    $events = parseSseEvents($response->streamedContent());
    $convEvent = collect($events)->firstWhere('type', 'conversation');

    expect($convEvent['run']['agent'])->toBe('registrar_auditor')
        ->and($convEvent['run']['status'])->toBe('completed');
});

it('injects authorized workspace and student context into the prompt without polluting persisted user messages', function (): void {
    $capturedPrompts = [];
    AdminExecutiveAgent::fake(function (string $prompt) use (&$capturedPrompts): string {
        $capturedPrompts[] = $prompt;

        return 'Student context received.';
    });

    Permission::firstOrCreate(['name' => 'View:Student', 'guard_name' => 'web']);
    $school = School::factory()->create(['name' => 'Main Campus']);
    $otherSchool = School::factory()->create(['name' => 'Other Campus']);
    app(TenantContext::class)->setCurrentSchool($school);

    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $admin->givePermissionTo('View:Student');

    $course = Course::factory()->create(['school_id' => $school->id, 'code' => 'BSCS']);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'student_id' => 2026991,
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'academic_year' => 3,
    ]);
    $foreignStudent = Student::factory()->create([
        'school_id' => $otherSchool->id,
        'student_id' => 2026992,
        'first_name' => 'Foreign',
        'last_name' => 'Record',
    ]);

    $response = $this->actingAs($admin)->post(portalUrlForAdministrators('/administrators/ai/chat'), [
        'request_id' => (string) Str::uuid7(),
        'agent' => 'admin_executive',
        'message' => 'What is this student\'s current year level?',
        'context' => [
            'page' => "/administrators/students/{$student->id}?tab=overview",
            'selected_student_ids' => [$student->id, $foreignStudent->id],
        ],
    ])->assertOk();

    $convEvent = collect(parseSseEvents($response->streamedContent()))->firstWhere('type', 'conversation');

    $capturedPrompt = $capturedPrompts[0] ?? '';

    // Streamed prompt received the authorized workspace context and student details, but NOT the foreign school's student
    expect($capturedPrompt)->toContain('[Workspace Context]')
        ->and($capturedPrompt)->toContain('Administrator Student Profile (/administrators/students/'.$student->id.')')
        ->and($capturedPrompt)->toContain('StudentNumber=2026991')
        ->and($capturedPrompt)->toContain('Hopper, Grace')
        ->and($capturedPrompt)->not->toContain('Foreign')
        ->and($capturedPrompt)->not->toContain('2026992');

    // Persisted user message content has no internal [Workspace Context] block
    $storedUser = ConversationMessage::query()
        ->where('conversation_id', $convEvent['conversationId'])
        ->where('role', 'user')
        ->firstOrFail();

    expect($storedUser->content)->toBe('What is this student\'s current year level?');
});

it('implements Approvable on all seven native Manage tools and keeps read-only MCP get actions un-gated', function (): void {
    $manageTools = [
        new ManageStudentTool,
        new ManageClassScheduleTool,
        new ManageClassEnrollmentTool,
        new ManageCurriculumSubjectTool,
        new ManageRoomTool,
        new ManageEnrollmentTool,
        new ManageSubjectEnrollmentTool,
    ];

    foreach ($manageTools as $tool) {
        expect($tool)->toBeInstanceOf(Approvable::class);
    }

    // Read-only action='get' does not request approval on native or MCP-wrapped tools
    $nativeStudent = new ManageStudentTool;
    expect($nativeStudent->shouldRequestApproval(new ToolRequest(['action' => 'get', 'student_id' => 1])))->toBeNull()
        ->and($nativeStudent->shouldRequestApproval(new ToolRequest(['action' => 'update', 'student_id' => 1])))->not->toBeNull();

    $mcpStudent = new ResilientMcpServerTool(new App\Mcp\Tools\ManageStudentTool);
    expect($mcpStudent->shouldRequestApproval(new ToolRequest(['action' => 'get', 'student_id' => 1])))->toBeNull()
        ->and($mcpStudent->shouldRequestApproval(new ToolRequest(['action' => 'update', 'student_id' => 1])))->not->toBeNull();
});

it('connects ApplyTuitionAdjustmentBatchTool to TuitionAdjustmentService with idempotency and rollback', function (): void {
    Permission::firstOrCreate(['name' => 'manage_tuition_fees', 'guard_name' => 'web']);
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);

    $bursar = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $bursar->givePermissionTo('manage_tuition_fees');
    $this->actingAs($bursar);

    $student = Student::factory()->create(['school_id' => $school->id, 'institution_id' => $school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_id' => $school->id,
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026-2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 20000,
        'overall_tuition' => 20000,
        'paid' => 5000,
        'total_balance' => 15000,
        'status' => 'partially_paid',
    ]);

    $service = app(TuitionAdjustmentService::class);
    $snapshot = $service->serialize($enrollment->fresh());
    $batchKey = (string) Str::uuid7();

    $tool = new ApplyTuitionAdjustmentBatchTool;
    $result = json_decode((string) $tool->handle(new ToolRequest([
        'batch_key' => $batchKey,
        'justification' => 'Approved academic scholarship credit',
        'adjustments' => [[
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'tuition_id' => $tuition->id,
            'state_hash' => $snapshot['state_hash'],
            'amount' => -2500.00,
            'reason' => 'Dean\'s List rebate',
        ]],
    ])), true);

    expect($result['success'])->toBeTrue()
        ->and($result['committed_count'])->toBe(1)
        ->and((float) $tuition->fresh()->overall_tuition)->toEqual(17500.0)
        ->and((float) $tuition->fresh()->total_balance)->toEqual(12500.0);

    // Replaying the exact same batch_key + adjustment is idempotent and does not double-deduct
    $replay = json_decode((string) $tool->handle(new ToolRequest([
        'batch_key' => $batchKey,
        'justification' => 'Approved academic scholarship credit',
        'adjustments' => [[
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'tuition_id' => $tuition->id,
            'state_hash' => $snapshot['state_hash'],
            'amount' => -2500.00,
            'reason' => 'Dean\'s List rebate',
        ]],
    ])), true);

    expect($replay['duplicate_count'])->toBe(1)
        ->and((float) $tuition->fresh()->overall_tuition)->toEqual(17500.0);
});

it('returns explicit unavailable states for unsupported transcript and policy simulation tools', function (): void {
    $transcript = json_decode((string) (new AnalyzeTranscriptDocumentTool)->handle(new ToolRequest([
        'document_name' => 'tor.pdf',
    ])), true);

    expect($transcript['available'])->toBeFalse()
        ->and($transcript['status'])->toBe('unavailable')
        ->and($transcript['subjects_detected'])->toBe([]);

    $simulation = json_decode((string) (new SimulatePolicyImpactTool)->handle(new ToolRequest([
        'minimum_gwa_threshold' => 2.25,
        'max_units_allowed' => 24,
    ])), true);

    expect($simulation['available'])->toBeFalse()
        ->and($simulation['status'])->toBe('unavailable')
        ->and($simulation['projected_eligible_count'])->toBeNull()
        ->and($simulation['retention_impact_percentage'])->toBeNull();
});

it('honors explicit room target_type for numeric room identifiers and scopes ManageRoomTool to active TenantContext school', function (): void {
    foreach (['Create:Room', 'View:Room'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $homeSchool = School::factory()->create(['name' => 'Home School']);
    $activeSchool = School::factory()->create(['name' => 'Active School']);
    app(TenantContext::class)->setCurrentSchool($activeSchool);

    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $homeSchool->id]);
    $admin->givePermissionTo(['Create:Room', 'View:Room']);
    $this->actingAs($admin);

    $roomTool = new ManageRoomTool;
    $created = json_decode((string) $roomTool->handle(new ToolRequest([
        'action' => 'create',
        'name' => '204',
        'building' => 'Science Wing',
        'capacity' => 40,
    ])), true);

    expect($created['success'])->toBeTrue();
    $room = Room::withoutGlobalScopes()->find($created['room']['id']);
    expect($room->school_id)->toBe($activeSchool->id);

    $class = Classes::factory()->create([
        'school_id' => $activeSchool->id,
        'subject_code' => 'PHYS101',
        'section' => 'A',
        'room_id' => $room->id,
    ]);
    Schedule::create([
        'class_id' => $class->id,
        'day_of_week' => 'Monday',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'room_id' => $room->id,
    ]);

    // Numeric identifier "204" with target_type="room" must query the room, not class_id=204
    $scheduleResult = json_decode((string) (new QueryTimetableScheduleTool)->handle(new ToolRequest([
        'target_type' => 'room',
        'identifier' => '204',
    ])), true);

    expect($scheduleResult['type'])->toBe('room')
        ->and($scheduleResult['rooms'][0]['name'])->toBe('204');
});

it('retains all 250 extracted rows of a 251-row sheet and reports accurate omitted counts', function (): void {
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('Large Roster');

    $rows = [['Student No', 'Full Name']];
    for ($i = 1; $i <= 251; $i++) {
        $rows[] = [sprintf('2026-%04d', $i), "Student #{$i}"];
    }
    $sheet->fromArray($rows);

    $path = tempnam(sys_get_temp_dir(), 'large-roster-').'.xlsx';
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $file = new UploadedFile($path, 'large_roster.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $processed = app(AiAttachmentProcessor::class)->process([$file], 'Check roster.', false);

    expect($processed['enrichedPrompt'])->toContain('2026-0001')
        ->and($processed['enrichedPrompt'])->toContain('2026-0250')
        ->and($processed['enrichedPrompt'])->not->toContain('2026-0251')
        ->and($processed['enrichedPrompt'])->toContain('Displaying first 250 of 251 rows from this spreadsheet; 1 rows omitted.');
});

it('rejects local MCP bearer configuration and exposes McpIntegrationStatusTool when discovery fails', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $this->actingAs($admin);

    $invalidLocal = McpServer::query()->create([
        'name' => 'local-bearer',
        'transport' => McpServer::TRANSPORT_LOCAL,
        'command' => 'php',
        'auth_type' => McpServer::AUTH_BEARER,
        'credentials' => ['token' => 'secret'],
        'allowed_agents' => ['admin_executive'],
        'enabled_tools' => ['ping'],
        'is_active' => true,
    ]);

    expect(fn () => app(McpClientFactory::class)->make($invalidLocal))
        ->toThrow(InvalidArgumentException::class, 'Bearer authentication requires the web transport');

    // Resolver catches the discovery failure and surfaces McpIntegrationStatusTool so the model can report the issue honestly
    $tools = app(ExternalMcpToolResolver::class)->forAgent('admin_executive');
    expect($tools)->toHaveCount(1)
        ->and($tools[0]->name())->toBe('mcp_integration_status');

    $diag = json_decode($tools[0]->handle(new ToolRequest([])), true);
    expect($diag['complete'])->toBeFalse()
        ->and($diag['issues'][0]['server'])->toBe('local-bearer')
        ->and($diag['issues'][0]['status'])->toBe('unavailable');
});
