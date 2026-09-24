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
