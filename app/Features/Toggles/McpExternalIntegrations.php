<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;
use App\Models\User;

final class McpExternalIntegrations implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function resolve(User $scope): bool
    {
        // New flag: legacy mode keeps the external tools that administrators
        // could already configure before this gate existed.
        return $scope->canAccessAdminPortal();
    }

    public function key(): string
    {
        return 'mcp-external-integrations';
    }

    public function name(): string
    {
        return 'External MCP Integrations';
    }

    public function summary(): string
    {
        return 'Expose administrator-configured external MCP tools to administrative AI agents. Server allowlists and approval gates still apply.';
    }

    public function audience(): string
    {
        return 'administrator';
    }

    public function badge(): ?string
    {
        return 'Integrations';
    }

    public function accent(): ?string
    {
        return 'text-cyan-500';
    }

    public function ctaLabel(): ?string
    {
        return 'Manage Integrations';
    }

    public function ctaUrl(): ?string
    {
        return '/administrators/system-management/ai';
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
