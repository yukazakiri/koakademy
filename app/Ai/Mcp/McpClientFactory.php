<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Mcp\Client;
use Laravel\Mcp\WebClient;
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
        if ($server->transport === McpServer::TRANSPORT_LOCAL && $server->auth_type === McpServer::AUTH_BEARER) {
            throw new InvalidArgumentException('Bearer authentication requires the web transport. Configure local MCP servers without HTTP bearer authentication.');
        }

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

            if ($token !== null && $client instanceof WebClient) {
                $client = $client->withToken($token);
            }
        }

        // withCache's first argument is a store, not a resource name. Its TTL
        // comes from remote response hints, so the registry instead caches only
        // discovery metadata using this server's configured TTL.
        return $client->withTimeout((float) $server->timeout_seconds)->withoutCache();
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
}
