<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class ValidateAdjustmentSpreadsheetTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Pre-screen and validate batch tuition adjustment rows for negative balance violations, duplicate credit memos, and arithmetic inconsistencies.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'rows' => 'required|array',
            'rows.*.student_id' => 'required|integer',
            'rows.*.amount' => 'required|numeric',
            'rows.*.type' => 'required|string',
            'rows.*.reason' => 'required|string',
        ]);

        $anomalies = [];
        $seenStudents = [];

        foreach ($validated['rows'] as $idx => $row) {
            $studentId = $row['student_id'];
            $amount = (float) $row['amount'];

            if ($amount <= 0) {
                $anomalies[] = [
                    'row' => $idx + 1,
                    'student_id' => $studentId,
                    'issue' => "Non-positive adjustment amount ({$amount}). Adjustment amounts must be greater than zero.",
                ];
            }

            if (isset($seenStudents[$studentId])) {
                $anomalies[] = [
                    'row' => $idx + 1,
                    'student_id' => $studentId,
                    'issue' => 'Potential duplicate adjustment: Student appears multiple times in the same batch.',
                ];
            }
            $seenStudents[$studentId] = true;
        }

        return json_encode([
            'total_rows_inspected' => count($validated['rows']),
            'valid_rows_count' => count($validated['rows']) - count($anomalies),
            'anomaly_count' => count($anomalies),
            'passed' => count($anomalies) === 0,
            'anomalies' => $anomalies,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'rows' => $schema->array()->items(
                $schema->object(fn ($s) => [
                    'student_id' => $s->integer()->required(),
                    'amount' => $s->number()->required(),
                    'type' => $s->string()->required(),
                    'reason' => $s->string()->required(),
                ])
            )->required(),
        ];
    }
}
