<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Services\CurriculumCapabilityResolver;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the authenticated staff account, selected school, current academic period, and MCP capabilities. Call this before working with school records.')]
#[IsReadOnly]
final class GetMyContextTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?CurriculumCapabilityResolver $curriculumCapabilities = null,
        private ?GeneralSettingsService $settings = null,
    ) {
        $this->curriculumCapabilities ??= app(CurriculumCapabilityResolver::class);
        $this->settings ??= app(GeneralSettingsService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $school = $this->school();
        $token = $user->currentAccessToken();

        return Response::structured([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role?->value,
            ],
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
            ],
            'academic_period' => [
                'school_year' => $this->settings->getCurrentSchoolYearString(),
                'semester' => $this->settings->getCurrentSemester(),
            ],
            'mcp' => [
                'token_name' => $token?->name,
                'abilities' => $token?->abilities ?? [],
                'can_write' => $this->settings->isMcpWriteEnabled()
                    && $this->tokenHasExplicitAbility($user, (string) config('api.mcp.abilities.write', 'mcp:write')),
            ],
            'curriculum_capabilities' => $this->curriculumCapabilities->forSchool($school)->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
