<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class AuditStudentProfileImportTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Audit a batch of prospective student profiles or import records for duplicate Learner Reference Numbers (LRN), invalid email syntax, and missing required biographical data.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'profiles' => 'required|array',
            'profiles.*.first_name' => 'required|string',
            'profiles.*.last_name' => 'required|string',
            'profiles.*.lrn' => 'nullable|string',
            'profiles.*.email' => 'nullable|email',
            'profiles.*.student_type' => 'nullable|string',
        ]);

        $anomalies = [];
        $existingLrns = Student::query()
            ->whereNotNull('lrn')
            ->pluck('lrn')
            ->flip()
            ->all();

        foreach ($validated['profiles'] as $index => $profile) {
            $lrn = $profile['lrn'] ?? null;
            if ($lrn) {
                if (mb_strlen($lrn) !== 12 || ! ctype_digit($lrn)) {
                    $anomalies[] = [
                        'row' => $index + 1,
                        'name' => "{$profile['first_name']} {$profile['last_name']}",
                        'issue' => "Invalid LRN '{$lrn}'. LRN must be exactly 12 numeric digits.",
                        'severity' => 'Error',
                    ];
                } elseif (isset($existingLrns[$lrn])) {
                    $anomalies[] = [
                        'row' => $index + 1,
                        'name' => "{$profile['first_name']} {$profile['last_name']}",
                        'issue' => "Duplicate LRN '{$lrn}' already exists in active student directory.",
                        'severity' => 'Critical Duplicate',
                    ];
                }
            }
        }

        return json_encode([
            'total_rows_audited' => count($validated['profiles']),
            'clean_rows_count' => count($validated['profiles']) - count($anomalies),
            'anomaly_count' => count($anomalies),
            'is_ready_for_import' => count($anomalies) === 0,
            'anomalies' => $anomalies,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'profiles' => $schema->array()->items(
                $schema->object(fn ($s) => [
                    'first_name' => $s->string()->required(),
                    'last_name' => $s->string()->required(),
                    'lrn' => $s->string(),
                    'email' => $s->string(),
                    'student_type' => $s->string(),
                ])
            )->required(),
        ];
    }
}
