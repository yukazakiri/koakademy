<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use App\Features\Toggles\McpExternalIntegrations;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Pennant\Feature;
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
     * inflates cost and degrades tool selection. When there are omissions or
     * failures, reserve one slot for explicit, model-readable diagnostics.
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
        $user = Auth::user();

        if (! $user instanceof User || ! Feature::for($user)->active(McpExternalIntegrations::class)) {
            return [];
        }

        $tools = [];
        $issues = [];

        foreach ($this->registry->serversForAgent($agent) as $server) {
            if (($server->enabled_tools ?? []) === []) {
                continue;
            }

            try {
                $discovered = $this->registry->tools($server);
            } catch (Throwable $e) {
                Log::warning('mcp.tools_unavailable', ['server' => $server->name, 'error' => $e->getMessage()]);
                $issues[] = ['server' => $server->name, 'status' => 'unavailable', 'message' => 'Discovery failed. Ask an administrator to check the server connection and authentication.'];

                continue;
            }

            foreach ($discovered as $tool) {
                if (! $server->exposesTool($tool->name)) {
                    continue;
                }

                try {
                    $tools[] = new ExternalMcpTool(
                        tool: $tool,
                        serverName: $server->name,
                        readOnly: ($tool->annotations['readOnlyHint'] ?? false) === true,
                    );
                } catch (Throwable $e) {
                    $issues[] = ['server' => $server->name, 'tool' => $tool->name, 'status' => 'unsupported_schema', 'message' => $e->getMessage()];
                }
            }
        }

        if (count($tools) > self::MAX_TOOLS_PER_AGENT || $issues !== []) {
            $omitted = array_splice($tools, self::MAX_TOOLS_PER_AGENT - 1);
            if ($omitted !== []) {
                $issues[] = [
                    'status' => 'tool_limit',
                    'limit' => self::MAX_TOOLS_PER_AGENT,
                    'omitted_count' => count($omitted),
                    'omitted_tools' => array_slice(array_map(fn (Tool $tool): string => $tool->name(), $omitted), 0, 20),
                    'message' => 'One catalog slot is reserved for diagnostics. Narrow the enabled tools for this agent to make other tools available.',
                ];
                Log::info('mcp.tools_truncated', ['agent' => $agent, 'skipped' => count($omitted), 'limit' => self::MAX_TOOLS_PER_AGENT]);
            }
            $tools[] = new McpIntegrationStatusTool(array_slice($issues, 0, 20));
        }

        return $tools;
    }
}
