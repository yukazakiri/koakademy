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

#[Description('Apply a curriculum import that the uploading administrator explicitly reviewed and approved. The import ID must be the staged draft ID. Never use this tool before the administrator approves the row-by-row review. Idempotent: an already applied import returns its existing program ID.')]
final class ApplyApprovedCurriculumImportTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $actor = $this->requireAdminWrite($request);
        $input = $request->validate(['import_id' => ['required', 'uuid']]);
        $imports = app(CurriculumImportService::class);

        return Response::structured($imports->apply($imports->find($input['import_id'], $actor), $actor));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['import_id' => $schema->string()->required()->description('Approved staged import UUID.')];
    }
}
