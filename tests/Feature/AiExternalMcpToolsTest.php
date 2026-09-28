<?php

declare(strict_types=1);

use App\Ai\Mcp\ExternalMcpTool;
use App\Ai\Mcp\ExternalMcpToolResolver;
use App\Ai\Mcp\McpClientFactory;
use App\Ai\Mcp\McpClientRegistry;
use App\Models\McpServer;
use App\Models\School;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Tool as ClientTool;
use Laravel\Mcp\Client\Schema\ToolResult;

/**
 * Behaviour of the external MCP integration: what reaches the model, what is
 * gated, and what a broken integration does to the chat turn.
 */
beforeEach(function (): void {
    $this->school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($this->school);
});

/**
 * Build a client tool primitive without a live client, which is enough to
 * exercise naming, description, and schema handling.
 *
 * @param  array<string, mixed>  $annotations
 * @param  array<string, mixed>  $inputSchema
 */
function clientTool(
    string $name = 'create-issue',
    ?string $description = 'Create a tracker issue.',
    array $annotations = [],
    array $inputSchema = [],
): ClientTool {
    return new ClientTool(
        client: null,
        name: $name,
        title: null,
        description: $description,
        inputSchema: $inputSchema,
        outputSchema: null,
        annotations: $annotations,
        meta: null,
    );
}

/**
 * A tool primitive that returns a fixed result, standing in for a reachable
 * server.
 *
 * The parent's destructor and connection state are stubbed out because the
 * stub never runs the constructor that would set them up.
 */
function stubbedClientTool(ToolResult $result, string $name = 'create-issue'): ClientTool
{
    $tool = clientTool($name);

    $client = new class($result) extends Client
    {
        public function __construct(private readonly ToolResult $result) {}

        public function callTool(ClientTool|string $tool, array $arguments = []): ToolResult
        {
            return $this->result;
        }

        public function connected(): bool
        {
            return true;
        }

        public function disconnect(): void
        {
            // no-op
        }
    };

    $reflection = new ReflectionClass(ClientTool::class);
    $property = $reflection->getProperty('client');
    $property->setValue($tool, $client);

    return $tool;
}

/**
 * Create a server for the school the test set as current.
 *
 * `school_id` is deliberately omitted: BelongsToSchool fills it from the
 * tenant context, so this also proves the model is tenant-scoped on write.
 */
function makeServer(array $attributes = []): McpServer
{
    return McpServer::query()->create(array_merge([
        'name' => 'tracker',
        'transport' => McpServer::TRANSPORT_WEB,
        'url' => 'https://mcp.example.test/tracker',
        'auth_type' => McpServer::AUTH_NONE,
        'allowed_agents' => ['admin_executive'],
        'enabled_tools' => ['create-issue'],
        'is_active' => true,
    ], $attributes));
}

it('scopes servers to the selected school', function (): void {
    $ours = makeServer();

    $otherSchool = School::factory()->create();
    $theirs = McpServer::query()->create([
        'school_id' => $otherSchool->id,
        'name' => 'tracker',
        'transport' => McpServer::TRANSPORT_WEB,
        'url' => 'https://mcp.example.test/other',
        'allowed_agents' => ['admin_executive'],
        'enabled_tools' => ['create-issue'],
    ]);

    // Both schools may legitimately have a server with the same name.
    expect($ours->exists)->toBeTrue()
        ->and($theirs->exists)->toBeTrue();

    // SchoolScope must keep them apart.
    expect(McpServer::query()->pluck('id')->all())->toBe([$ours->id])
        ->and(McpServer::withoutSchoolScope()->count())->toBe(2);
});

it('encrypts credentials at rest and never exposes them in a payload', function (): void {
    $server = makeServer([
        'auth_type' => McpServer::AUTH_BEARER,
        'credentials' => ['token' => 'super-secret-token'],
    ]);

    $raw = DB::table('mcp_servers')->where('id', $server->id)->first();

    expect($raw->credentials)->not->toContain('super-secret-token')
        ->and($server->bearerToken())->toBe('super-secret-token')
        ->and($server->toArray())->not->toHaveKey('credentials');
});

it('requires a transport target before a server is considered usable', function (): void {
    expect(makeServer()->isUsable())->toBeTrue()
        ->and(makeServer(['name' => 'no-url', 'url' => null])->isUsable())->toBeFalse()
        ->and(makeServer([
            'name' => 'local-no-command',
            'transport' => McpServer::TRANSPORT_LOCAL,
            'url' => null,
            'command' => null,
        ])->isUsable())->toBeFalse()
        ->and(makeServer(['name' => 'switched-off', 'is_active' => false])->isUsable())->toBeFalse();
});

it('exposes nothing until tools and agents are both chosen', function (): void {
    $server = makeServer();

    expect($server->exposesTool('create-issue'))->toBeTrue()
        ->and($server->exposesTool('delete-everything'))->toBeFalse()
        ->and($server->visibleToAgent('admin_executive'))->toBeTrue()
        ->and($server->visibleToAgent('campus_support'))->toBeFalse();

    // A server added with no allowlist configured hands the model nothing.
    $blank = makeServer(['name' => 'blank', 'enabled_tools' => [], 'allowed_agents' => []]);

    expect($blank->exposesTool('create-issue'))->toBeFalse()
        ->and($blank->visibleToAgent('admin_executive'))->toBeFalse();
});

/**
 * A tool the server did not mark read-only must stop at the approval card.
 * The framework's own McpTool wrapper has no approval gate at all, so this is
 * the control that keeps a third party from acting without a human.
 */
it('gates a mutating external tool behind approval', function (): void {
    $tool = new ExternalMcpTool(clientTool(annotations: []), 'tracker', readOnly: false);

    $approval = $tool->shouldRequestApproval(new Request(['title' => 'Close the registrar gap']));

    expect($approval)->not->toBeNull()
        ->and($approval->reason)->toContain('create-issue')
        ->and($approval->reason)->toContain('tracker');
});

it('lets a server-declared read-only tool run without approval', function (): void {
    $tool = new ExternalMcpTool(
        clientTool(annotations: ['readOnlyHint' => true]),
        'tracker',
        readOnly: true,
    );

    expect($tool->shouldRequestApproval(new Request([])))->toBeNull();
});

/**
 * The hint is a claim by the remote server. It relaxes the approval gate, but
 * nothing else about the tool is trusted: the name is server-qualified and the
 * description states the provenance so the model treats the answer as external.
 */
it('marks external results as untrusted and qualifies the tool name', function (): void {
    $tool = new ExternalMcpTool(clientTool(name: 'list-issues'), 'My Tracker', readOnly: true);

    expect($tool->name())->toBe('mcp_my-tracker_list-issues')
        ->and((string) $tool->description())
        ->toContain('My Tracker')
        ->toContain('untrusted');
});

it('does not send a token to a server that is not configured for one', function (): void {
    $unauthenticated = makeServer(['name' => 'plain', 'auth_type' => McpServer::AUTH_NONE]);
    $withToken = makeServer([
        'name' => 'secured',
        'auth_type' => McpServer::AUTH_BEARER,
        'credentials' => ['token' => 'tok'],
    ]);

    // The factory never inspects credentials for a server that is not
    // configured for bearer auth, so a stray column cannot leak a token.
    expect($unauthenticated->bearerToken())->toBeNull()
        ->and($withToken->bearerToken())->toBe('tok');

    $factory = new McpClientFactory;
    expect($factory->make($unauthenticated))->toBeInstanceOf(Client::class)
        ->and($factory->make($withToken))->toBeInstanceOf(Client::class);
});

it('reports an unreachable external system instead of failing the turn', function (): void {
    // A primitive with no client bound raises when called, which is exactly the
    // shape of a transport failure.
    $tool = new ExternalMcpTool(clientTool(), 'tracker', readOnly: true);

    $data = json_decode((string) $tool->handle(new Request([])), true);

    expect($data['error'])->toBeTrue()
        ->and($data['system'])->toBe('tracker')
        ->and($data['message'])->toContain('could not be reached');
});

it('surfaces an error result from the remote system rather than reporting success', function (): void {
    $tool = new ExternalMcpTool(
        stubbedClientTool(new ToolResult(
            content: [['type' => 'text', 'text' => 'Repository archived']],
            isError: true,
        )),
        'tracker',
        readOnly: true,
    );

    $data = json_decode((string) $tool->handle(new Request([])), true);

    expect($data['error'])->toBeTrue()
        ->and($data['message'])->toBe('Repository archived');
});

it('caps an oversized external response', function (): void {
    $tool = new ExternalMcpTool(
        stubbedClientTool(new ToolResult(
            content: [['type' => 'text', 'text' => str_repeat('A', 40_000)]],
            isError: false,
        ), 'dump-everything'),
        'tracker',
        readOnly: true,
    );

    $data = json_decode((string) $tool->handle(new Request([])), true);

    expect($data['untrusted'])->toBeTrue()
        ->and($data['result']['truncated'] ?? false)->toBeTrue()
        ->and(mb_strlen((string) $data['result']['preview']))->toBeLessThanOrEqual(20_100);
});

/**
 * The resolver is the single place that decides what the model can reach, so
 * the allowlist is enforced there rather than trusting the discovery response.
 */
it('resolves no external tools when no server is configured', function (): void {
    expect(app(ExternalMcpToolResolver::class)->forAgent('admin_executive'))->toBe([]);
});

it('skips a server that cannot be built without breaking the agent', function (): void {
    // Configured for local transport with no command: unusable, so the
    // resolver must skip it rather than raise.
    makeServer([
        'transport' => McpServer::TRANSPORT_LOCAL,
        'url' => null,
        'command' => null,
    ]);

    expect(app(ExternalMcpToolResolver::class)->forAgent('admin_executive'))->toBe([]);
});

it('refuses to build a client for a server outside the tenant', function (): void {
    $otherSchool = School::factory()->create();
    McpServer::query()->create([
        'school_id' => $otherSchool->id,
        'name' => 'not-ours',
        'transport' => McpServer::TRANSPORT_WEB,
        'url' => 'https://mcp.example.test/other',
        'allowed_agents' => ['admin_executive'],
        'enabled_tools' => ['create-issue'],
    ]);

    expect(app(McpClientRegistry::class)->client('not-ours'))->toBeNull();
});

it('reports a discovery failure instead of throwing', function (): void {
    // Pointing at an unroutable address makes tools/list fail for real.
    $server = makeServer(['url' => 'https://127.0.0.1:9/unreachable']);

    $result = app(McpClientRegistry::class)->discover($server);

    expect($result['tools'])->toBe([])
        ->and($result['error'])->not->toBeNull()
        ->and($server->refresh()->last_error)->not->toBeNull();
});
