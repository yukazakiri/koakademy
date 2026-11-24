<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use App\Models\McpServer;
use App\Services\Ai\AiEndpointGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            ])],
            // Required when creating a bearer-authenticated server or switching
            // a server without a stored token to bearer auth. Optional on edit
            // when a token is already stored, since the field is write-only.
            'token' => [
                'nullable', 'string', 'max:4096',
                Rule::requiredIf(
                    $this->input('auth_type') === McpServer::AUTH_BEARER
                    && ($this->isCreating() || $this->existingServer()?->bearerToken() === null),
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
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->isCreating() && $this->currentSchoolId() === null) {
                    $validator->errors()->add(
                        'school_id',
                        'Select an active school before configuring an external MCP server.',
                    );
                }

                if ($this->input('transport') === McpServer::TRANSPORT_LOCAL
                    && $this->input('auth_type') === McpServer::AUTH_BEARER) {
                    $validator->errors()->add(
                        'auth_type',
                        'Bearer authentication requires the web transport. Configure local MCP servers without HTTP bearer authentication.',
                    );
                }

                if ($this->input('transport') === McpServer::TRANSPORT_WEB && is_string($this->input('url')) && filled($this->input('url'))) {
                    $guard = app(AiEndpointGuard::class);
                    $reason = $guard->unsafeReason($guard->normalize($this->input('url')), resolveDns: false);

                    if ($reason !== null) {
                        $validator->errors()->add('url', $guard->rejectionMessage($reason));
                    }
                }
            },
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
