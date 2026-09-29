<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Str;
use Laravel\Mcp\Client;
use Throwable;

/**
 * Builds a connected-by-construction MCP client from a stored server record.
 *
 * Kept separate from the registry so the connection policy (timeout, cache,
 * credentials) can be asserted on its own, and so a test can substitute a
 * client without touching the registrar that decides which servers apply.
 */
final class McpClientFactory
{
    public function make(McpServer $server): Client
    {
        $client = match ($server->transport) {
            McpServer::TRANSPORT_LOCAL => Client::local(
                (string) $server->command,
                array_values((array) $server->command_arguments),
            ),
            default => Client::web((string) $server->url),
        };

        // An unauthenticated server must never inherit a bearer token meant
        // for a different one, and a token is only ever sent when the server
        // is configured for it.
        if ($server->auth_type === McpServer::AUTH_BEARER) {
            $token = $server->bearerToken();

            if ($token !== null) {
                $client = $client->withToken($token);
            }
        }

        return $client
            ->withTimeout((float) $server->timeout_seconds)
            // Discovery is a network round trip on every prompt, and the tool
            // list changes rarely, so cache it per server.
            ->withCache('tools', $this->cacheKeyFor($server));
    }

    /**
     * Records a connection attempt so an administrator can tell a broken
     * integration from one that was simply never reachable.
     */
    public function probe(McpServer $server): void
    {
        try {
            $this->make($server)->connect();

            $server->forceFill([
                'last_connected_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $server->forceFill(['last_error' => Str::limit($e->getMessage(), 500)])->save();

            throw $e;
        }
    }

    private function cacheKeyFor(McpServer $server): string
    {
        return 'mcp-tools:'.$server->school_id.':'.$server->name;
    }
}
