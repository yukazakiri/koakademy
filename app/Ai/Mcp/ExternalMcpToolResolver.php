<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Throwable;

/**
 * Resolves the external MCP tools an agent is allowed to see.
 *
 * This is the single place that decides what leaves the building, so the
 * rules are stated once and applied uniformly:
 *
 * - a server must be active, usable, and pointed at the agent;
 * - only tools the administrator ticked are exposed, never the whole catalog;
 * - a server that cannot be reached is skipped, never partially applied;
 * - a hard cap stops one integration from crowding the model's tool budget.
 */
final class ExternalMcpToolResolver
{
    /**
     * Upper bound on external tools per agent.
     *
     * Every advertised tool is sent on every request, so an unbounded list
     * inflates cost and degrades the model's ability to pick the right tool
     * among the KoAkademy ones. Truncation is recorded so the agent can say
     * the integration is partially available rather than pretending the list
     * is complete.
     */
    public const int MAX_TOOLS_PER_AGENT = 12;

    public function __construct(
        private readonly McpClientRegistry $registry,
    ) {}

    /**
     * @return list<Tool>
     */
    public function forAgent(string $agent): array
    {
        $tools = [];
        $skipped = 0;

        foreach ($this->registry->serversForAgent($agent) as $server) {
            foreach ($this->toolsFor($server) as $tool) {
                if (count($tools) >= self::MAX_TOOLS_PER_AGENT) {
                    $skipped++;

                    continue 2;
                }

                $tools[] = $tool;
            }
        }

        if ($skipped > 0) {
            Log::info('mcp.tools_truncated', [
                'agent' => $agent,
                'skipped' => $skipped,
                'limit' => self::MAX_TOOLS_PER_AGENT,
            ]);
        }

        return $tools;
    }

    /**
     * @return list<ExternalMcpTool>
     */
    private function toolsFor(McpServer $server): array
    {
        $client = $this->registry->client($server->name);

        if ($client === null) {
            return [];
        }

        try {
            $discovered = $client->tools();
        } catch (Throwable $e) {
            // An unreachable integration degrades to "this capability is not
            // available right now"; it must not abort the chat turn.
            Log::warning('mcp.tools_unavailable', [
                'server' => $server->name,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $discovered
            ->filter(fn ($tool): bool => $server->exposesTool($tool->name))
            ->map(fn ($tool): ExternalMcpTool => new ExternalMcpTool(
                tool: $tool,
                serverName: $server->name,
                readOnly: ($tool->annotations['readOnlyHint'] ?? false) === true,
            ))
            ->values()
            ->all();
    }
}
