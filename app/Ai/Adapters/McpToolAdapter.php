<?php

declare(strict_types=1);

namespace App\Ai\Adapters;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool as AiToolContract;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool as McpToolContract;
use ReflectionClass;
use Stringable;
use Throwable;

final class McpToolAdapter implements AiToolContract
{
    private McpToolContract $mcpTool;

    private string $name;

    private string $description;

    public function __construct(McpToolContract $mcpTool, ?string $name = null, ?string $description = null)
    {
        $this->mcpTool = $mcpTool;
        $this->name = $name ?? class_basename($mcpTool);
        $this->description = $description ?? $this->resolveDescription($mcpTool);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): Stringable|string
    {
        return $this->description;
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->mcpTool->schema($schema);
    }

    public function handle(AiRequest $request): Stringable|string
    {
        try {
            $mcpRequest = new McpRequest($request->all());
            $responseFactory = $this->mcpTool->handle($mcpRequest);

            $structured = $responseFactory->getStructuredContent();
            if (! empty($structured)) {
                return json_encode($structured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            $texts = [];
            foreach ($responseFactory->responses() as $response) {
                if (isset($response->content->text)) {
                    $texts[] = $response->content->text;
                }
            }

            return ! empty($texts) ? implode("\n", $texts) : json_encode(['status' => 'success'], JSON_PRETTY_PRINT);
        } catch (Throwable $e) {
            return json_encode([
                'error' => true,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    private function resolveDescription(McpToolContract $tool): string
    {
        $ref = new ReflectionClass($tool);
        $attributes = $ref->getAttributes(Description::class);
        if (! empty($attributes)) {
            /** @var Description $instance */
            $instance = $attributes[0]->newInstance();

            return (string) ($instance->value ?? class_basename($tool));
        }

        return class_basename($tool);
    }
}
