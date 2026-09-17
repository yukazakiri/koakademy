<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GenerateAnalyticsChartTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Format and generate a visual interactive analytics chart (Bar, Area, Ring, Line, or Gauge) for display inside the administrative conversation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'chart_type' => 'required|string|in:bar,area,ring,line,gauge',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'data' => 'required|array',
            'data.*.label' => 'required|string',
            'data.*.value' => 'required|numeric',
            'data.*.color' => 'nullable|string',
            'metric_unit' => 'nullable|string',
        ]);

        return json_encode([
            '_type' => 'chart_artifact',
            'chart_type' => $validated['chart_type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'metric_unit' => $validated['metric_unit'] ?? '',
            'data' => $validated['data'],
            'generated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'chart_type' => $schema->string()->enum(['bar', 'area', 'ring', 'line', 'gauge'])->required(),
            'title' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'data' => $schema->array()->items(
                $schema->object(fn ($s) => [
                    'label' => $s->string()->required(),
                    'value' => $s->number()->required(),
                    'color' => $s->string(),
                ])
            )->required(),
            'metric_unit' => $schema->string(),
        ];
    }
}
