<?php

declare(strict_types=1);

use App\Ai\Mcp\ExternalMcpToolResolver;
use App\Enums\UserRole;
use App\Models\McpServer;
use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The administration surface for external MCP servers: who may configure an
 * integration, what the UI is allowed to see, and that one school cannot
 * reach another's servers.
 */
beforeEach(function (): void {
    $this->withoutVite();

    $this->school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($this->school);

    $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
});

function mcpPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'tracker',
        'description' => 'Issue tracker integration',
        'transport' => 'web',
        'url' => 'https://mcp.example.test/tracker',
        'auth_type' => 'none',
        'enabled_tools' => ['create-issue'],
        'allowed_agents' => ['admin_executive'],
        'is_active' => true,
    ], $overrides);
}

it('lists configured servers on the AI settings page', function (): void {
    McpServer::query()->create(mcpPayload(['auth_type' => 'bearer', 'credentials' => ['token' => 'secret']]));

    $this->actingAs($this->admin)
        ->get('/administrators/system-management/ai')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/system-management/ai')
            ->has('mcp_servers', 1)
            ->where('mcp_servers.0.name', 'tracker')
            // Presence only, so opening the page cannot leak the secret.
            ->where('mcp_servers.0.has_token', true)
            ->missing('mcp_servers.0.token')
            ->missing('mcp_servers.0.credentials')
            ->where('mcp_servers.0.exposes_nothing', false)
        );
});

it('flags a server that is configured but reaches nothing', function (): void {
    McpServer::query()->create(mcpPayload(['enabled_tools' => [], 'allowed_agents' => []]));

    $this->actingAs($this->admin)
        ->get('/administrators/system-management/ai')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('mcp_servers.0.exposes_nothing', true)
            ->where('mcp_servers.0.assigned_to_no_agent', true)
        );
});

it('creates a server without exposing it to the model by default', function (): void {
    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload(['enabled_tools' => []]))
        ->assertRedirect();

    $server = McpServer::query()->where('name', 'tracker')->firstOrFail();

    expect($server->school_id)->toBe($this->school->id)
        ->and($server->enabled_tools)->toBe([])
        ->and(app(ExternalMcpToolResolver::class)->forAgent('admin_executive'))->toBe([]);
});

it('rejects a server name that cannot be used as a client key', function (): void {
    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload(['name' => 'Not A Key']))
        ->assertSessionHasErrors('name');

    expect(McpServer::query()->count())->toBe(0);
});

it('rejects an unknown assistant key', function (): void {
    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload(['allowed_agents' => ['everyone']]))
        ->assertSessionHasErrors('allowed_agents.0');
});

it('requires a url for a remote server and a command for a local one', function (): void {
    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload(['name' => 'a', 'url' => null]))
        ->assertSessionHasErrors('url');

    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload([
            'name' => 'b',
            'transport' => 'local',
            'url' => null,
            'command' => null,
        ]))
        ->assertSessionHasErrors('command');
});

it('rejects a duplicate name within the same school', function (): void {
    McpServer::query()->create(mcpPayload());

    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload())
        ->assertSessionHasErrors('name');
});

it('allows two schools to each have a server with the same name', function (): void {
    McpServer::query()->create(mcpPayload());

    $otherSchool = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($otherSchool);

    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload())
        ->assertRedirect();

    expect(McpServer::withoutSchoolScope()->count())->toBe(2);
});

it('keeps the stored token when an edit leaves it blank', function (): void {
    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload([
            'auth_type' => 'bearer',
            'token' => 'original-token',
        ]))
        ->assertRedirect();

    $server = McpServer::query()->where('name', 'tracker')->firstOrFail();
    expect($server->bearerToken())->toBe('original-token');

    // Editing the tool list must not silently un-authenticate the integration.
    $this->actingAs($this->admin)
        ->put("/administrators/system-management/ai/mcp-servers/{$server->id}", mcpPayload([
            'auth_type' => 'bearer',
            'token' => null,
            'enabled_tools' => ['create-issue', 'list-issues'],
        ]))
        ->assertRedirect();

    $server->refresh();

    expect($server->bearerToken())->toBe('original-token')
        ->and($server->enabled_tools)->toBe(['create-issue', 'list-issues']);
});

it('encrypts the token at rest', function (): void {
    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload([
            'auth_type' => 'bearer',
            'token' => 'plaintext-should-not-persist',
        ]));

    $raw = DB::table('mcp_servers')->where('name', 'tracker')->first();

    expect($raw->credentials)->not->toContain('plaintext-should-not-persist')
        ->and(McpServer::query()->where('name', 'tracker')->firstOrFail()->bearerToken())
        ->toBe('plaintext-should-not-persist');
});

it('blocks a user without AI settings permission', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($student)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload())
        ->assertForbidden();
});

it('deletes a server', function (): void {
    $server = McpServer::query()->create(mcpPayload());

    $this->actingAs($this->admin)
        ->delete("/administrators/system-management/ai/mcp-servers/{$server->id}")
        ->assertRedirect();

    expect(McpServer::query()->withoutSchoolScope()->count())->toBe(0);
});

it('reports a discovery failure instead of a server error', function (): void {
    $server = McpServer::query()->create(mcpPayload(['url' => 'https://127.0.0.1:9/unreachable']));

    $this->actingAs($this->admin)
        ->postJson("/administrators/system-management/ai/mcp-servers/{$server->id}/discover")
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'tools']);
});

it('will not let one school update or delete another school server', function (): void {
    $otherSchool = School::factory()->create();
    $theirs = McpServer::query()->withoutSchoolScope()->create([
        'school_id' => $otherSchool->id,
        'name' => 'theirs',
        'transport' => 'web',
        'url' => 'https://mcp.example.test/theirs',
        'auth_type' => 'none',
        'allowed_agents' => ['admin_executive'],
        'enabled_tools' => ['x'],
    ]);

    $this->actingAs($this->admin)
        ->put("/administrators/system-management/ai/mcp-servers/{$theirs->id}", mcpPayload(['name' => 'hijacked']))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->delete("/administrators/system-management/ai/mcp-servers/{$theirs->id}")
        ->assertNotFound();

    expect(McpServer::withoutSchoolScope()->find($theirs->id))->not->toBeNull();
});

it('rejects unsupported oauth auth_type and local bearer combinations', function (): void {
    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload([
            'auth_type' => McpServer::AUTH_OAUTH,
        ]))
        ->assertSessionHasErrors('auth_type');

    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload([
            'transport' => McpServer::TRANSPORT_LOCAL,
            'url' => null,
            'command' => 'php',
            'auth_type' => McpServer::AUTH_BEARER,
            'token' => 'secret-token',
        ]))
        ->assertSessionHasErrors('auth_type');

    expect(McpServer::query()->count())->toBe(0);
});

it('rejects unsafe external mcp server urls to prevent ssrf', function (string $unsafeUrl): void {
    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload([
            'url' => $unsafeUrl,
        ]))
        ->assertSessionHasErrors('url');

    expect(McpServer::query()->count())->toBe(0);
})->with([
    'plain http' => 'http://mcp.example.test/tracker',
    'loopback ip' => 'https://127.0.0.1/mcp',
    'cloud metadata ip' => 'https://169.254.169.254/latest/meta-data/',
    'private lan ip' => 'https://10.0.0.15/mcp',
]);

it('requires an active school context instead of crashing with a database error', function (): void {
    app(TenantContext::class)->clear();

    $this->actingAs($this->admin)
        ->post('/administrators/system-management/ai/mcp-servers', mcpPayload())
        ->assertSessionHasErrors('school_id');

    expect(McpServer::withoutSchoolScope()->count())->toBe(0);
});

it('requires a token when switching an unauthenticated server to bearer auth', function (): void {
    $server = McpServer::query()->create(mcpPayload(['auth_type' => McpServer::AUTH_NONE]));

    $this->actingAs($this->admin)
        ->put("/administrators/system-management/ai/mcp-servers/{$server->id}", mcpPayload([
            'auth_type' => McpServer::AUTH_BEARER,
            'token' => null,
        ]))
        ->assertSessionHasErrors('token');
});
