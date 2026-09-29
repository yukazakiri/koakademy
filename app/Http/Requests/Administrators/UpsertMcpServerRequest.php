<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use App\Models\McpServer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or updating an external MCP server.
 *
 * The tool and agent allowlists are validated as closed vocabularies rather
 * than free strings, so a typo cannot silently create a server that exposes
 * nothing and looks configured.
 */
final class UpsertMcpServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAi', GeneralSetting::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $serverId = $this->existingServer()?->id;

        return [
            'name' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                // Also the Mcp::registerClient() key, so it must be unique.
                Rule::unique('mcp_servers', 'name')
                    ->where('school_id', $this->currentSchoolId())
                    ->ignore($serverId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'transport' => ['required', Rule::in([McpServer::TRANSPORT_WEB, McpServer::TRANSPORT_LOCAL])],
            'url' => ['nullable', 'url', 'max:500', 'required_if:transport,web'],
            'command' => ['nullable', 'string', 'max:500', 'required_if:transport,local'],
            'command_arguments' => ['nullable', 'array', 'max:20'],
            'command_arguments.*' => ['string', 'max:200'],
            'auth_type' => ['required', Rule::in([
                McpServer::AUTH_NONE,
                McpServer::AUTH_BEARER,
                McpServer::AUTH_OAUTH,
            ])],
            // Required when creating a bearer-authenticated server, but optional
            // on update: the token is write-only, so a blank field on an edit
            // means "keep the stored one" rather than "clear it".
            'token' => [
                'nullable', 'string', 'max:4096',
                Rule::requiredIf(
                    $this->isCreating() && $this->input('auth_type') === McpServer::AUTH_BEARER,
                ),
            ],
            'enabled_tools' => ['nullable', 'array', 'max:200'],
            'enabled_tools.*' => ['string', 'max:120'],
            'allowed_agents' => ['nullable', 'array', 'max:10'],
            'allowed_agents.*' => [Rule::in($this->agentKeys())],
            'is_active' => ['nullable', 'boolean'],
            'timeout_seconds' => ['nullable', 'integer', 'between:1,120'],
            'cache_ttl_seconds' => ['nullable', 'integer', 'between:0,3600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Use lowercase letters, numbers, hyphens, and underscores only, so the name can be used as an MCP client key.',
            'enabled_tools.max' => 'Select at most 200 tools. Select none to keep the server disconnected from the AI assistants.',
        ];
    }

    /**
     * The server being updated, if this is an update rather than a create.
     */
    private function existingServer(): ?McpServer
    {
        $route = $this->route('mcpServer');

        return $route instanceof McpServer ? $route : null;
    }

    private function isCreating(): bool
    {
        return $this->existingServer() === null;
    }

    /**
     * @return list<string>
     */
    private function agentKeys(): array
    {
        return ['admin_executive', 'registrar_auditor', 'bursar_finance', 'campus_support'];
    }

    private function currentSchoolId(): ?int
    {
        return app(\App\Services\TenantContext::class)->getCurrentSchoolId();
    }
}
