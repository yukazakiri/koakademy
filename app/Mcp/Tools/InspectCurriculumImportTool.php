<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Services\CurriculumImportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Inspect a previously uploaded curriculum import draft. Returns the program title, subject rows, warnings and matching program candidates. Does not modify records; the administrator must review and confirm the import in chat.')]
#[IsReadOnly]
final class InspectCurriculumImportTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $actor = $this->requireAdmin($request);
        $this->requirePermission($actor, 'View:Course', 'You are not permitted to view curriculum imports.');
        $input = $request->validate(['import_id' => ['required', 'uuid']]);
        $imports = app(CurriculumImportService::class);

        return Response::structured($imports->review($imports->find($input['import_id'], $actor), $actor));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['import_id' => $schema->string()->required()->description('Identifier returned by the curriculum import upload.')];
    }
}
