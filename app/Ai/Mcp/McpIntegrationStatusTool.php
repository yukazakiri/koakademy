<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/** Read-only diagnostics for integrations that cannot expose their full catalog. */
final class McpIntegrationStatusTool implements Tool
{
    /** @param list<array<string, mixed>> $issues */
    public function __construct(private readonly array $issues) {}

    public function name(): string
    {
        return 'mcp_integration_status';
    }

    public function description(): string
    {
        return 'Some configured external MCP tools are unavailable or omitted from this agent catalog. Read these diagnostics before claiming the capability is absent or complete. Report the limitation and ask an administrator to fix the connection, schema, or enabled tool selection.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        return (string) json_encode([
            'complete' => false,
            'issues' => $this->issues,
            'guidance' => 'Do not claim omitted tools ran. Ask an administrator to narrow the enabled tool selection or repair the integration, then start a new turn.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
