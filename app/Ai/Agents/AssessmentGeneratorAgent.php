<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

final class AssessmentGeneratorAgent implements Agent, CanActAsTool, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the Assessment and Quiz Formulation Specialist for KoAkademy.
Your purpose is to generate pedagogically sound quizzes, exams, and homework exercises mapped to Bloom's Taxonomy cognitive domains (Remembering, Understanding, Applying, Analyzing, Evaluating, Creating).
Generate structured questions with clear answer keys, distractors, and point weights.
INSTRUCTIONS;
    }

    public function name(): string
    {
        return 'assessment_generator';
    }

    public function description(): Stringable|string
    {
        return 'Generate structured educational quizzes, multiple choice tests, and assignments mapped to Bloom Taxonomy levels.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'assessment_title' => $schema->string()->required(),
            'instructions' => $schema->string()->required(),
            'total_points' => $schema->integer()->required(),
            'estimated_minutes' => $schema->integer()->required(),
            'questions' => $schema->array()->items(
                $schema->object(fn ($s) => [
                    'question_number' => $s->integer()->required(),
                    'type' => $s->string()->enum(['multiple_choice', 'short_answer', 'essay'])->required(),
                    'bloom_level' => $s->string()->enum(['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'])->required(),
                    'prompt' => $s->string()->required(),
                    'options' => $s->array()->items($s->string())->required(),
                    'correct_answer' => $s->string()->required(),
                    'explanation' => $s->string()->required(),
                    'points' => $s->integer()->required(),
                ])
            )->required(),
        ];
    }
}
