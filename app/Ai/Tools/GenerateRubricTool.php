<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GenerateRubricTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Generate an educational grading rubric based on learning competencies, assignment criteria, and maximum point scale.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'subject_title' => 'required|string',
            'assignment_title' => 'required|string',
            'max_points' => 'required|integer|min:10|max:100',
        ]);

        return json_encode([
            'subject' => $validated['subject_title'],
            'assignment' => $validated['assignment_title'],
            'max_points' => $validated['max_points'],
            'criteria' => [
                [
                    'dimension' => 'Content Accuracy & Mastery',
                    'weight_percent' => 40,
                    'levels' => [
                        ['label' => 'Exemplary', 'points' => (int) round($validated['max_points'] * 0.40)],
                        ['label' => 'Proficient', 'points' => (int) round($validated['max_points'] * 0.32)],
                        ['label' => 'Developing', 'points' => (int) round($validated['max_points'] * 0.24)],
                    ],
                ],
                [
                    'dimension' => 'Critical Thinking & Analysis',
                    'weight_percent' => 30,
                    'levels' => [
                        ['label' => 'Exemplary', 'points' => (int) round($validated['max_points'] * 0.30)],
                        ['label' => 'Proficient', 'points' => (int) round($validated['max_points'] * 0.24)],
                        ['label' => 'Developing', 'points' => (int) round($validated['max_points'] * 0.18)],
                    ],
                ],
                [
                    'dimension' => 'Structure, Formatting & Clarity',
                    'weight_percent' => 30,
                    'levels' => [
                        ['label' => 'Exemplary', 'points' => (int) round($validated['max_points'] * 0.30)],
                        ['label' => 'Proficient', 'points' => (int) round($validated['max_points'] * 0.24)],
                        ['label' => 'Developing', 'points' => (int) round($validated['max_points'] * 0.18)],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject_title' => $schema->string()->required(),
            'assignment_title' => $schema->string()->required(),
            'max_points' => $schema->integer()->required(),
        ];
    }
}
