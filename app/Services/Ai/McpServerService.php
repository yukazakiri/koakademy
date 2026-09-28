<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Mcp\McpClientRegistry;
use App\Models\McpServer;
use Illuminate\Support\Str;

/**
 * Reads and writes the external MCP server configuration, including the
 * credential handling the form deliberately does not.
 *
 * The bearer token is write-only from the UI's point of view: it is stored
 * encrypted and never sent back to the browser, and submitting an empty token
 * on an update keeps the existing one rather than clearing it. An
 * administrator who cannot read a credential cannot leak it by opening the
 * page.
 */
final class McpServerService
{
    public function __construct(
        private readonly McpClientRegistry $registry,
    ) {}

    /**
     * Servers for the administration list, without credentials.
     *
     * @return list<array<string, mixed>>
     */
    public function forAdministration(): array
    {
        return McpServer::query()
            ->orderBy('name')
            ->get()
            ->map(fn (McpServer $server): array => $this->present($server))
            ->all();
    }

    /**
     * Discover a server's tools so they can be selected.
     *
     * @return array{tools: list<array<string, mixed>>, error: ?string}
     */
    public function discover(McpServer $server): array
    {
        return $this->registry->discover($server);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): McpServer
    {
        $server = new McpServer;
        $server->fill($this->attributes($validated));

        if (($validated['token'] ?? null) !== null) {
            $server->credentials = ['token' => $validated['token']];
        }

        $server->save();

        return $server;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(McpServer $server, array $validated): McpServer
    {
        $server->fill($this->attributes($validated));

        // An empty token on an update means "leave the stored one alone", so an
        // administrator editing the tool list cannot silently un-authenticate
        // the integration.
        if (filled($validated['token'] ?? null)) {
            $server->credentials = ['token' => $validated['token']];
        } elseif (($validated['auth_type'] ?? null) === McpServer::AUTH_NONE) {
            $server->credentials = null;
        }

        $server->save();

        return $server;
    }

    public function delete(McpServer $server): void
    {
        $server->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'transport' => $validated['transport'],
            'url' => $validated['transport'] === McpServer::TRANSPORT_WEB ? $validated['url'] : null,
            'command' => $validated['transport'] === McpServer::TRANSPORT_LOCAL ? $validated['command'] : null,
            'command_arguments' => $validated['command_arguments'] ?? null,
            'auth_type' => $validated['auth_type'],
            'enabled_tools' => $validated['enabled_tools'] ?? [],
            'allowed_agents' => $validated['allowed_agents'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
            'timeout_seconds' => $validated['timeout_seconds'] ?? 15,
            'cache_ttl_seconds' => $validated['cache_ttl_seconds'] ?? 300,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(McpServer $server): array
    {
        return [
            'id' => $server->id,
            'public_id' => $server->public_id,
            'name' => $server->name,
            'description' => $server->description,
            'transport' => $server->transport,
            'url' => $server->url,
            'command' => $server->command,
            'command_arguments' => $server->command_arguments ?? [],
            'auth_type' => $server->auth_type,
            // Presence only. The value is write-only.
            'has_token' => $server->bearerToken() !== null,
            'enabled_tools' => $server->enabled_tools ?? [],
            'allowed_agents' => $server->allowed_agents ?? [],
            'is_active' => $server->is_active,
            'timeout_seconds' => $server->timeout_seconds,
            'cache_ttl_seconds' => $server->cache_ttl_seconds,
            'is_usable' => $server->isUsable(),
            'exposes_nothing' => ($server->enabled_tools ?? []) === [],
            'assigned_to_no_agent' => ($server->allowed_agents ?? []) === [],
            'last_connected_at' => $server->last_connected_at?->toIso8601String(),
            'last_error' => $server->last_error === null ? null : Str::limit($server->last_error, 300),
        ];
    }
}
