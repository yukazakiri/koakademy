<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Ai\AiSettingsService;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

beforeEach(function (): void {
    $this->withoutVite();
    app(AiSettingsService::class)->clearCache();
});

it('renders the administrator AI chat page for authorized admins', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administrators/ai/index')
            ->has('initialConversation')
            ->has('initialConversationId')
            ->where('hideMobileNavigation', true)
        );
});

it('forbids non-administrative users from accessing the administrator AI chat page', function (): void {
    $studentUser = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($studentUser)
        ->get(portalUrlForAdministrators('/administrators/ai'))
        ->assertForbidden();
});

it('lists paginated conversations owned by the authenticated admin', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $otherAdmin = User::factory()->create(['role' => UserRole::Admin]);

    // Admin's conversations
    Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Enrollment Analytics 2026',
    ]);
    Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Graduation Holds Audit',
    ]);

    // Other user's conversation
    Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $otherAdmin->id,
        'participant_type' => $otherAdmin->getMorphClass(),
        'title' => 'Confidential Financial Report',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators('/administrators/ai/conversations'))
        ->assertOk()
        ->json();

    $titles = collect($response['data'])->pluck('title')->all();
    expect($titles)->toContain('Enrollment Analytics 2026')
        ->and($titles)->toContain('Graduation Holds Audit')
        ->and($titles)->not->toContain('Confidential Financial Report');
});

it('filters conversations by search query', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Tuition Revenue Forecast',
    ]);
    Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Faculty Loading Matrix',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators('/administrators/ai/conversations?query=Revenue'))
        ->assertOk()
        ->json();

    $titles = collect($response['data'])->pluck('title')->all();
    expect($titles)->toContain('Tuition Revenue Forecast')
        ->and($titles)->not->toContain('Faculty Loading Matrix');
});

it('retrieves conversation messages and formats tool calls and approvals', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $conv = Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Clearance Evaluation',
    ]);

    // User message
    ConversationMessage::query()->create([
        'id' => (string) str()->uuid(),
        'conversation_id' => $conv->id,
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'agent' => 'App\Ai\Agents\AdminExecutiveAgent',
        'role' => 'user',
        'content' => 'Show clearance summary',
        'attachments' => '[]',
        'steps' => [],
        'status' => 'completed',
        'usage' => '[]',
        'meta' => '[]',
    ]);

    // Assistant message with a tool call and result
    ConversationMessage::query()->create([
        'id' => (string) str()->uuid(),
        'conversation_id' => $conv->id,
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'agent' => 'App\Ai\Agents\AdminExecutiveAgent',
        'role' => 'assistant',
        'content' => 'Here is the clearance report.',
        'attachments' => '[]',
        'steps' => [[
            'content' => 'Here is the clearance report.',
            'tool_calls' => [[
                'id' => 'call_123',
                'name' => 'QueryCampusAnalyticsTool',
                'arguments' => ['metric' => 'clearances'],
                'result' => ['cleared' => 450, 'holds' => 22],
            ]],
            'reasoning' => '',
            'replay_blocks' => [],
            'provider_tool_calls' => [],
        ]],
        'status' => 'completed',
        'usage' => '[]',
        'meta' => json_encode([
            'citations' => [
                ['title' => 'Registrar Policy', 'url' => 'https://koakademy.test/handbook'],
            ],
        ]),
    ]);

    $response = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators("/administrators/ai/conversations/{$conv->id}"))
        ->assertOk()
        ->json();

    expect($response['conversation']['id'])->toBe($conv->id)
        ->and($response['messages'])->toHaveCount(2)
        ->and($response['messages'][0]['role'])->toBe('user')
        ->and($response['messages'][1]['role'])->toBe('assistant')
        ->and($response['messages'][1]['toolCalls'][0]['toolName'])->toBe('QueryCampusAnalyticsTool')
        ->and($response['messages'][1]['toolCalls'][0]['state'])->toBe('output-available')
        ->and($response['messages'][1]['sources'][0]['url'])->toBe('https://koakademy.test/handbook');
});

it('renames an administrator conversation title', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $conv = Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Old Title',
    ]);

    $this->actingAs($admin)
        ->patchJson(portalUrlForAdministrators("/administrators/ai/conversations/{$conv->id}"), [
            'title' => 'Updated Strategic Briefing',
        ])
        ->assertOk();

    expect($conv->fresh()->title)->toBe('Updated Strategic Briefing');
});

it('deletes an administrator conversation and its messages', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $conv = Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Temporary Scratchpad',
    ]);

    ConversationMessage::query()->create([
        'id' => (string) str()->uuid(),
        'conversation_id' => $conv->id,
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'agent' => 'App\Ai\Agents\AdminExecutiveAgent',
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => '[]',
        'steps' => [],
        'status' => 'completed',
        'usage' => '[]',
        'meta' => '[]',
    ]);

    $this->actingAs($admin)
        ->deleteJson(portalUrlForAdministrators("/administrators/ai/conversations/{$conv->id}"))
        ->assertOk();

    expect(Conversation::query()->find($conv->id))->toBeNull()
        ->and(ConversationMessage::query()->where('conversation_id', $conv->id)->count())->toBe(0);
});

it('forbids unauthorized access to another user\'s conversation', function (): void {
    $adminA = User::factory()->create(['role' => UserRole::Admin]);
    $adminB = User::factory()->create(['role' => UserRole::Admin]);

    $conv = Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $adminA->id,
        'participant_type' => $adminA->getMorphClass(),
        'title' => 'Private Workspace A',
    ]);

    $this->actingAs($adminB)
        ->getJson(portalUrlForAdministrators("/administrators/ai/conversations/{$conv->id}"))
        ->assertNotFound();

    $this->actingAs($adminB)
        ->patchJson(portalUrlForAdministrators("/administrators/ai/conversations/{$conv->id}"), [
            'title' => 'Hacked Title',
        ])
        ->assertNotFound();

    $this->actingAs($adminB)
        ->deleteJson(portalUrlForAdministrators("/administrators/ai/conversations/{$conv->id}"))
        ->assertNotFound();
});

it('validates payload on administrative AI chat endpoint', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators('/administrators/ai/chat'), [
            'agent' => 'invalid_agent_name',
            'message' => 'Hello',
        ])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators('/administrators/ai/chat'), [
            'agent' => 'admin_executive',
            // Missing both message and decisions
        ])
        ->assertUnprocessable();
});

it('streams chat response and emits conversation event with id and title', function (): void {
    App\Ai\Agents\AdminExecutiveAgent::fake([
        'Welcome to institutional intelligence.',
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/ai/chat'), [
            'agent' => 'admin_executive',
            'message' => 'Provide an executive summary of current campus metrics.',
        ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/event-stream');

    $content = $response->streamedContent();
    expect($content)->toContain('"type":"conversation"')
        ->and($content)->toContain('"conversationId"')
        ->and($content)->toContain('"type":"text-delta"')
        ->and($content)->toContain('"delta":"Welcome"')
        ->and($content)->toContain('intelligence.')
        ->and($content)->toContain('data: [DONE]');
});

it('paginates conversations beyond the first page', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    // Create 25 conversations
    for ($i = 1; $i <= 25; $i++) {
        Conversation::query()->create([
            'id' => (string) str()->uuid(),
            'participant_id' => $admin->id,
            'participant_type' => $admin->getMorphClass(),
            'title' => "Archived Conversation #{$i}",
            'updated_at' => now()->subMinutes(30 - $i),
        ]);
    }

    $page1 = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators('/administrators/ai/conversations?page=1'))
        ->assertOk()
        ->json();

    expect($page1['current_page'])->toBe(1)
        ->and($page1['last_page'])->toBe(2)
        ->and($page1['total'])->toBe(25)
        ->and($page1['data'])->toHaveCount(20);

    $page2 = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators('/administrators/ai/conversations?page=2'))
        ->assertOk()
        ->json();

    expect($page2['current_page'])->toBe(2)
        ->and($page2['data'])->toHaveCount(5);
});

it('streams chat response with designated specialist agent', function (): void {
    App\Ai\Agents\RegistrarAuditAgent::fake([
        'Registrar audit completed successfully.',
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/ai/chat'), [
            'agent' => 'registrar_auditor',
            'message' => 'Audit senior graduation clearances.',
        ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('"type":"conversation"')
        ->and($content)->toContain('"delta":"Registrar"')
        ->and($content)->toContain('audit')
        ->and($content)->toContain('data: [DONE]');
});

it('returns 503 service unavailable when AI features are disabled in system settings', function (): void {
    app(AiSettingsService::class)->merge([
        'enabled' => false,
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/ai/chat'), [
            'agent' => 'admin_executive',
            'message' => 'Hello',
        ])
        ->assertStatus(503);
});

it('resolves room, student, and faculty schedules via QueryTimetableScheduleTool', function (): void {
    $room = App\Models\Room::create(['name' => 'Lecture Hall 101', 'is_active' => true]);
    $faculty = App\Models\Faculty::factory()->create([
        'first_name' => 'Alan',
        'last_name' => 'Turing',
        'faculty_id_number' => 'FAC-101',
    ]);
    $class = App\Models\Classes::factory()->create([
        'subject_code' => 'CS101',
        'section' => 'BSCS-1A',
        'school_year' => '2026-2027',
        'semester' => 1,
        'room_id' => $room->id,
        'faculty_id' => $faculty->id,
    ]);
    App\Models\Schedule::create([
        'class_id' => $class->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'room_id' => $room->id,
    ]);

    $tool = new App\Ai\Tools\QueryTimetableScheduleTool();

    // Query room schedule
    $roomResult = json_decode((string) $tool->handle(new Laravel\Ai\Tools\Request([
        'target_type' => 'room',
        'identifier' => 'Lecture Hall 101',
        'check_availability' => true,
    ])), true);

    expect($roomResult['type'])->toBe('room')
        ->and($roomResult['rooms'][0]['name'])->toBe('Lecture Hall 101')
        ->and($roomResult['rooms'][0]['schedules'][0]['subject_code'])->toBe('CS101')
        ->and($roomResult['rooms'][0]['available_windows'])->not->toBeEmpty();

    // Query faculty schedule
    $facultyResult = json_decode((string) $tool->handle(new Laravel\Ai\Tools\Request([
        'target_type' => 'faculty',
        'identifier' => 'Turing',
    ])), true);

    expect($facultyResult['type'])->toBe('faculty')
        ->and($facultyResult['faculty'][0]['name'])->toBe($faculty->full_name)
        ->and($facultyResult['faculty'][0]['schedules'][0]['subject_code'])->toBe('CS101');

    // Query class schedule by compound identifier
    $classResult1 = json_decode((string) $tool->handle(new Laravel\Ai\Tools\Request([
        'target_type' => 'class',
        'identifier' => 'CS101 Section BSCS-1A',
    ])), true);

    expect($classResult1['type'])->toBe('class')
        ->and($classResult1['count'])->toBe(1)
        ->and($classResult1['classes'][0]['class_id'])->toBe($class->id)
        ->and($classResult1['classes'][0]['schedules'][0]['day_of_week'])->toBe('Tuesday');

    // Query class schedule by numeric class ID string
    $classResult2 = json_decode((string) $tool->handle(new Laravel\Ai\Tools\Request([
        'target_type' => 'class',
        'identifier' => (string) $class->id,
    ])), true);

    expect($classResult2['count'])->toBe(1)
        ->and($classResult2['classes'][0]['class_id'])->toBe($class->id);

    // Query class schedule by explicit class_id parameter
    $classResult3 = json_decode((string) $tool->handle(new Laravel\Ai\Tools\Request([
        'class_id' => $class->id,
    ])), true);

    expect($classResult3['count'])->toBe(1)
        ->and($classResult3['classes'][0]['class_id'])->toBe($class->id);

    // Verify LookupClassSchedulesTool handles compound queries as well
    $lookupTool = new App\Ai\Tools\LookupClassSchedulesTool();
    $lookupResult = json_decode((string) $lookupTool->handle(new Laravel\Ai\Tools\Request([
        'query' => 'CS101 BSCS-1A',
    ])), true);

    expect($lookupResult['count'])->toBeGreaterThanOrEqual(1)
        ->and($lookupResult['classes'][0]['class_id'])->toBe($class->id);
});

it('manages student profiles with approval gates via ManageStudentTool', function (): void {
    foreach (['View:Student', 'Create:Student', 'Update:Student'] as $perm) {
        Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->givePermissionTo(['View:Student', 'Create:Student', 'Update:Student']);
    $this->actingAs($admin);

    $course = App\Models\Course::factory()->create(['code' => 'BSIT', 'title' => 'Information Technology']);

    $tool = new App\Ai\Tools\ManageStudentTool();

    // Create student
    $createRequest = new Laravel\Ai\Tools\Request([
        'action' => 'create',
        'first_name' => 'Katherine',
        'last_name' => 'Johnson',
        'email' => 'katherine.johnson@example.com',
        'course_code' => 'BSIT',
        'academic_year' => 1,
        'student_type' => 'college',
    ]);

    $createResult = json_decode((string) $tool->handle($createRequest), true);
    expect($createResult['success'])->toBeTrue()
        ->and($createResult['student']['name'])->toBe('Johnson, Katherine ')
        ->and($createResult['student']['email'])->toBe('katherine.johnson@example.com');

    $studentId = $createResult['student']['id'];

    // Update student
    $updateRequest = new Laravel\Ai\Tools\Request([
        'action' => 'update',
        'student_id' => $studentId,
        'academic_year' => 2,
        'status' => 'enrolled',
    ]);
    $updateResult = json_decode((string) $tool->handle($updateRequest), true);
    expect($updateResult['success'])->toBeTrue()
        ->and($updateResult['student']['status'])->toBe('enrolled');

    // Get student details without approval
    $getResult = json_decode((string) $tool->handle(new Laravel\Ai\Tools\Request([
        'action' => 'get',
        'student_id' => $studentId,
    ])), true);
    expect($getResult['found'])->toBeTrue()
        ->and($getResult['program'])->toBe('BSIT');

    // Archive student
    $archiveResult = json_decode((string) $tool->handle(new Laravel\Ai\Tools\Request([
        'action' => 'archive',
        'student_id' => $studentId,
        'reason' => 'Leave of absence',
    ])), true);
    expect($archiveResult['success'])->toBeTrue();
    $savedStatus = App\Models\Student::find($studentId)->status;
    expect($savedStatus instanceof BackedEnum ? $savedStatus->value : $savedStatus)->toBe('dropped');
});

it('manages curriculum subjects and class schedules via AI tools', function (): void {
    foreach (['Create:Subject', 'Update:Subject', 'Delete:Subject', 'View:Subject', 'Create:Classes', 'Update:Classes', 'Delete:Classes', 'View:Classes'] as $perm) {
        Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->givePermissionTo(['Create:Subject', 'Update:Subject', 'Delete:Subject', 'View:Subject', 'Create:Classes', 'Update:Classes', 'Delete:Classes', 'View:Classes']);
    $this->actingAs($admin);

    $course = App\Models\Course::factory()->create(['code' => 'BSCS', 'title' => 'Computer Science']);
    $room = App\Models\Room::create(['name' => 'Lab 305', 'is_active' => true]);

    $subjectTool = new App\Ai\Tools\ManageCurriculumSubjectTool();
    $classTool = new App\Ai\Tools\ManageClassScheduleTool();

    // Create subject
    $subjectResult = json_decode((string) $subjectTool->handle(new Laravel\Ai\Tools\Request([
        'action' => 'create',
        'code' => 'CS305',
        'title' => 'Operating Systems',
        'units' => 3,
        'academic_year' => 3,
        'semester' => 1,
        'course_code' => 'BSCS',
    ])), true);

    expect($subjectResult['success'])->toBeTrue()
        ->and($subjectResult['subject']['code'])->toBe('CS305');

    // Create class schedule
    $classResult = json_decode((string) $classTool->handle(new Laravel\Ai\Tools\Request([
        'action' => 'create_class',
        'subject_code' => 'CS305',
        'section' => 'BSCS-3A',
        'room_id' => $room->id,
        'day_of_week' => 'Thursday',
        'start_time' => '14:00',
        'end_time' => '16:00',
    ])), true);

    expect($classResult['success'])->toBeTrue()
        ->and($classResult['class']['subject_code'])->toBe('CS305');

    $classId = $classResult['class']['id'];

    // Reschedule class
    $rescheduleResult = json_decode((string) $classTool->handle(new Laravel\Ai\Tools\Request([
        'action' => 'reschedule',
        'class_id' => $classId,
        'day_of_week' => 'Friday',
        'start_time' => '15:00',
        'end_time' => '17:00',
    ])), true);

    expect($rescheduleResult['success'])->toBeTrue()
        ->and($rescheduleResult['schedule']['day_of_week'])->toBe('Friday');
});

it('adapts built-in MCP tools seamlessly into AI agent tools', function (): void {
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'View:Course', 'guard_name' => 'web']);
    $school = App\Models\School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $admin->givePermissionTo('View:Course');
    $this->actingAs($admin);
    app(App\Services\TenantContext::class)->setCurrentSchool($school);

    $course = App\Models\Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSIT',
        'title' => 'Information Technology',
    ]);
    App\Models\Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT101',
        'title' => 'IT Fundamentals',
        'academic_year' => 1,
        'semester' => 1,
    ]);

    $adapter = new App\Ai\Adapters\McpToolAdapter(new App\Mcp\Tools\GetCourseCurriculumTool());

    expect($adapter->name())->toBe('GetCourseCurriculumTool')
        ->and((string) $adapter->description())->toContain('curriculum');

    $result = json_decode((string) $adapter->handle(new Laravel\Ai\Tools\Request([
        'course_id' => $course->id,
    ])), true);

    expect($result['course']['code'])->toBe('BSIT')
        ->and($result['subjects_count'])->toBeGreaterThanOrEqual(1);
});

it('formats class enrollments compactly without unneeded payload bloat', function (): void {
    $class = App\Models\Classes::factory()->create([
        'subject_code' => 'GE-1',
        'section' => 'B',
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);

    $student = App\Models\Student::factory()->create([
        'first_name' => 'Stephanie',
        'last_name' => 'Sto Domingo',
        'student_id' => '208382',
        'gender' => 'Male',
        'academic_year' => 1,
    ]);

    App\Models\ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    $tool = new App\Ai\Tools\GetClassEnrollmentsTool();
    $rawResponse = (string) $tool->handle(new Laravel\Ai\Tools\Request([
        'class_id' => $class->id,
    ]));

    $data = json_decode($rawResponse, true);

    expect($data['class_id'])->toBe($class->id)
        ->and($data['subject_code'])->toBe('GE-1')
        ->and($data['section'])->toBe('B')
        ->and($data['enrolled_count'])->toBe(1)
        ->and($data['students'][0]['student_number'])->toBe('208382')
        ->and($data['students'][0]['name'])->toBe('Stephanie Sto Domingo')
        ->and($data['students'][0]['status'])->toBe('Active');

    // Ensure raw response does not contain unnecessary verbose fields like full personalInfo arrays
    expect(mb_strlen($rawResponse))->toBeLessThan(1000);
});

it('supports batch student upserts, class schedules, and curriculum subjects via AI tools', function (): void {
    foreach (['Create:Student', 'Update:Student', 'Create:Subject', 'Update:Subject', 'Create:Classes', 'Update:Classes', 'Update:StudentEnrollment'] as $perm) {
        Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->givePermissionTo(['Create:Student', 'Update:Student', 'Create:Subject', 'Update:Subject', 'Create:Classes', 'Update:Classes', 'Update:StudentEnrollment']);
    $this->actingAs($admin);

    $course = App\Models\Course::factory()->create(['code' => 'BSHM', 'title' => 'Hospitality Management']);
    $room = App\Models\Room::create(['name' => 'Kitchen Lab 1', 'is_active' => true]);

    // 1. Batch upsert students (one new, one update)
    $existingStudent = App\Models\Student::factory()->create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria.santos@example.com',
        'course_id' => $course->id,
        'academic_year' => 1,
    ]);

    $studentTool = new App\Ai\Tools\ManageStudentTool();
    $studentResult = json_decode((string) $studentTool->handle(new Laravel\Ai\Tools\Request([
        'action' => 'batch_upsert',
        'students' => [
            [
                'student_id' => $existingStudent->id,
                'first_name' => 'Maria Clara',
                'last_name' => 'Santos',
                'email' => 'maria.santos@example.com',
                'academic_year' => 2,
                'status' => 'enrolled',
            ],
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan.delacruz@example.com',
                'course_code' => 'BSHM',
                'academic_year' => 1,
                'status' => 'applicant',
            ],
        ],
    ])), true);

    expect($studentResult['success'])->toBeTrue()
        ->and($studentResult['created_count'])->toBe(1)
        ->and($studentResult['updated_count'])->toBe(1);

    expect($existingStudent->refresh()->first_name)->toBe('Maria Clara')
        ->and($existingStudent->academic_year)->toBe(2);

    expect(App\Models\Student::where('email', 'juan.delacruz@example.com')->exists())->toBeTrue();

    // 2. Batch upsert curriculum subjects
    $subjectTool = new App\Ai\Tools\ManageCurriculumSubjectTool();
    $subResult = json_decode((string) $subjectTool->handle(new Laravel\Ai\Tools\Request([
        'action' => 'batch_upsert',
        'subjects' => [
            [
                'code' => 'HPC 1',
                'title' => 'Fundamentals in Food Service',
                'units' => 3,
                'lecture' => 2,
                'laboratory' => 1,
                'academic_year' => 1,
                'semester' => 1,
                'course_code' => 'BSHM',
            ],
            [
                'code' => 'THC 1',
                'title' => 'Macro Perspective in Tourism',
                'units' => 3,
                'academic_year' => 1,
                'semester' => 1,
                'course_code' => 'BSHM',
            ],
        ],
    ])), true);

    expect($subResult['success'])->toBeTrue()
        ->and($subResult['created_count'])->toBe(2);

    // 3. Batch create class schedules
    $classTool = new App\Ai\Tools\ManageClassScheduleTool();
    $classResult = json_decode((string) $classTool->handle(new Laravel\Ai\Tools\Request([
        'action' => 'batch_create',
        'classes' => [
            [
                'subject_code' => 'HPC 1',
                'section' => 'BSHM-1A',
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'room_name' => 'Kitchen Lab 1',
            ],
            [
                'subject_code' => 'THC 1',
                'section' => 'BSHM-1A',
                'day_of_week' => 'Wednesday',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'room_name' => 'Kitchen Lab 1',
            ],
        ],
    ])), true);

    expect($classResult['success'])->toBeTrue()
        ->and($classResult['created_classes_count'])->toBe(2);

    expect(App\Models\Classes::where('subject_code', 'HPC 1')->where('section', 'BSHM-1A')->exists())->toBeTrue()
        ->and(App\Models\Schedule::where('day_of_week', 'Monday')->where('start_time', '08:00:00')->exists())->toBeTrue();
});

it('extracts multi-sheet spreadsheets cleanly with titles and tables for dynamic AI agent comprehension', function (): void {
    $book = new PhpOffice\PhpSpreadsheet\Spreadsheet();

    // Sheet 1: Students roster
    $sheet1 = $book->getActiveSheet();
    $sheet1->setTitle('Students Roster');
    $sheet1->setCellValue('A1', 'KOAKADEMY OFFICIAL ENROLLED STUDENTS');
    $sheet1->setCellValue('A2', 'First Name');
    $sheet1->setCellValue('B2', 'Last Name');
    $sheet1->setCellValue('C2', 'Email');
    $sheet1->setCellValue('D2', 'Program');
    $sheet1->setCellValue('A3', 'Jose');
    $sheet1->setCellValue('B3', 'Rizal');
    $sheet1->setCellValue('C3', 'jose.rizal@example.com');
    $sheet1->setCellValue('D3', 'BSHM');

    // Sheet 2: Class Schedules
    $sheet2 = $book->createSheet();
    $sheet2->setTitle('Class Schedules');
    $sheet2->setCellValue('A1', 'Subject Code');
    $sheet2->setCellValue('B1', 'Section');
    $sheet2->setCellValue('C1', 'Day');
    $sheet2->setCellValue('D1', 'Time');
    $sheet2->setCellValue('E1', 'Room');
    $sheet2->setCellValue('A2', 'HPC 1');
    $sheet2->setCellValue('B2', 'BSHM-1A');
    $sheet2->setCellValue('C2', 'Monday');
    $sheet2->setCellValue('D2', '08:00-10:00');
    $sheet2->setCellValue('E2', 'Kitchen Lab');

    $path = tempnam(sys_get_temp_dir(), 'test-sheets-').'.xlsx';
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    $uploaded = new Illuminate\Http\UploadedFile($path, 'master_records.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $processor = app(App\Services\Ai\AiAttachmentProcessor::class);
    $processed = $processor->process([$uploaded], 'Please analyze the uploaded files and help me update records.');

    expect($processed['enrichedPrompt'])->toContain('Sheet: \'Students Roster\'')
        ->and($processed['enrichedPrompt'])->toContain('Sheet: \'Class Schedules\'')
        ->and($processed['enrichedPrompt'])->toContain('jose.rizal@example.com')
        ->and($processed['enrichedPrompt'])->toContain('Kitchen Lab')
        ->and($processed['enrichedPrompt'])->toContain('Document Header / Metadata');
});
