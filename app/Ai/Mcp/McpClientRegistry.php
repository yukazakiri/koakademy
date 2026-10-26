<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Tool as ClientTool;
use Laravel\Mcp\Facades\Mcp;
use RuntimeException;
use Throwable;

/**
 * Decides which external MCP servers apply to an agent, and registers each one
 * as a named MCP client so it can be resolved anywhere in the request.
 *
 * Every filter here is a security decision, not a convenience: a server is
 * only registered when it is active, usable, and explicitly pointed at the
 * agent, and only allowlisted tools are ever handed to the model.
 */
final class McpClientRegistry
{
    public function __construct(
        private readonly McpClientFactory $factory,
    ) {}

    /**
     * Servers that apply to the given agent, in a stable order.
     *
     * @return Collection<int, McpServer>
     */
    public function serversForAgent(string $agent): Collection
    {
        return McpServer::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->filter(fn (McpServer $server): bool => $server->isUsable() && $server->visibleToAgent($agent))
            ->values();
    }

    /**
     * Resolve a registered client by name.
     *
     * Registers on demand so a server added at runtime is usable without a
     * redeploy, which a provider-boot registration alone would not allow.
     */
    public function client(string $name): ?Client
    {
        $server = McpServer::query()->active()->where('name', $name)->first();

        if (! $server instanceof McpServer || ! $server->isUsable()) {
            return null;
        }

        Mcp::registerClient($name, fn (): Client => $this->factory->make($server));

        try {
            return Mcp::client($name);
        } catch (Throwable $e) {
            // A server that cannot be built must not take the whole turn down.
            Log::warning('mcp.client_build_failed', [
                'server' => $name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cache plain discovery metadata, never connected clients or credentials.
     * Rebind cached tools to the current tenant's freshly configured client.
     *
     * @return Collection<int, ClientTool>
     */
    public function tools(McpServer $server): Collection
    {
        $client = $this->client($server->name);

        if ($client === null) {
            throw new RuntimeException('The MCP client could not be configured. Check the server transport and authentication settings.');
        }

        $fetch = fn (): array => $client->tools()->map(fn (ClientTool $tool): array => [
            'name' => $tool->name,
            'title' => $tool->title,
            'description' => $tool->description,
            'inputSchema' => $tool->inputSchema,
            'outputSchema' => $tool->outputSchema,
            'annotations' => $tool->annotations,
            '_meta' => $tool->meta,
        ])->values()->all();

        $ttl = max(0, (int) $server->cache_ttl_seconds);
        // Connection edits, credential rotation and TTL changes invalidate the
        // catalog immediately, without putting secrets into a cache key.
        $key = 'mcp-tools:'.$server->school_id.':'.hash('sha256', serialize([
            $server->getKey(), $server->name, $server->transport, $server->url,
            $server->command, $server->command_arguments, $server->auth_type,
            $server->getRawOriginal('credentials'), $server->timeout_seconds, $ttl,
        ]));

        $payloads = $ttl > 0 ? Cache::remember($key, $ttl, $fetch) : $fetch();

        return collect($payloads)->map(fn (array $payload): ClientTool => ClientTool::from($client, $payload));
    }

    /**
     * Discover every tool a server advertises, for the administration screen.
     *
     * Deliberately not filtered by the allowlist: an administrator has to see
     * what exists before deciding what to enable.
     *
     * @return array{tools: list<array{name: string, title: ?string, description: ?string, enabled: bool}>, error: ?string}
     */
    public function discover(McpServer $server): array
    {
        if (! $server->isUsable()) {
            return ['tools' => [], 'error' => 'This server is not fully configured.'];
        }

        try {
            $tools = $this->tools($server);
        } catch (Throwable $e) {
            $server->forceFill(['last_error' => Str::limit($e->getMessage(), 500)])->save();

            return ['tools' => [], 'error' => $e->getMessage()];
        }

        $server->forceFill([
            'last_connected_at' => now(),
            'last_error' => null,
        ])->save();

        return [
            'tools' => $tools->map(fn ($tool): array => [
                'name' => $tool->name,
                'title' => $tool->title,
                'description' => $tool->description,
                'enabled' => $server->exposesTool($tool->name),
            ])->values()->all(),
            'error' => null,
        ];
    }
}
