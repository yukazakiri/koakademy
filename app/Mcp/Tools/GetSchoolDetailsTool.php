<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Department;
use App\Services\CurriculumCapabilityResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get comprehensive organization details for the active school, including curriculum capabilities, active departments, and contact information.')]
#[IsReadOnly]
final class GetSchoolDetailsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?CurriculumCapabilityResolver $curriculumCapabilities = null)
    {
        $this->curriculumCapabilities ??= app(CurriculumCapabilityResolver::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $this->requireRead($request);
        $school = $this->school();

        $departments = Department::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Department $dept): array => [
                'id' => $dept->id,
                'code' => $dept->code,
                'name' => $dept->name,
            ])
            ->values()
            ->all();

        return Response::structured([
            'id' => $school->id,
            'name' => $school->name,
            'code' => $school->code,
            'country_code' => $school->country_code,
            'school_level' => $school->school_level?->value,
            'curriculum_framework' => $school->curriculum_framework,
            'dean_name' => $school->dean_name,
            'dean_email' => $school->dean_email,
            'location' => $school->location,
            'phone' => $school->phone,
            'email' => $school->email,
            'departments' => $departments,
            'curriculum_capabilities' => $this->curriculumCapabilities->forSchool($school)->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
