<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;
use App\Models\User;

final class McpServerAccess implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function resolve(User $scope): bool
    {
        // New flag: legacy mode is the upgrade-compatibility path, where every
        // authenticated account keeps the MCP access it had before this gate.
        return true;
    }

    public function key(): string
    {
        return 'mcp-server-access';
    }

    public function name(): string
    {
        return 'Inbound MCP Server Access';
    }

    public function summary(): string
    {
        return 'Allow authenticated users to connect agents to the KoAkademy MCP server. Token abilities and permissions still apply.';
    }

    public function audience(): string
    {
        return 'administrator';
    }

    public function badge(): ?string
    {
        return 'MCP';
    }

    public function accent(): ?string
    {
        return 'text-sky-500';
    }

    public function ctaLabel(): ?string
    {
        return null;
    }

    public function ctaUrl(): ?string
    {
        return null;
    }

    public function steps(): array
    {
        return [];
    }

    public function category(): string
    {
        return 'MCP & Integrations';
    }
}
