<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use App\Services\StudentConfigurationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class AuditStudentProfileImportTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Audit a batch of prospective student profiles or import records against the school\'s configured external/regional learner identifier policy, checking for invalid identifier formats, duplicate identifiers or student IDs, malformed or duplicate email addresses, and missing required fields.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'profiles' => 'required|array',
            'profiles.*.first_name' => 'required|string',
            'profiles.*.last_name' => 'required|string',
            'profiles.*.external_identifier' => 'nullable|string',
            'profiles.*.lrn' => 'nullable|string',
            'profiles.*.student_id' => 'nullable|string',
            'profiles.*.email' => 'nullable|string',
            'profiles.*.student_type' => 'nullable|string',
        ]);

        $configService = app(StudentConfigurationService::class);
        $schoolConfig = $configService->get();
        $defaultStudentType = (string) ($schoolConfig['default_type'] ?? 'college');
        $emailFieldPolicy = is_array($schoolConfig['fields']['email'] ?? null)
            ? $schoolConfig['fields']['email']
            : [];
        $emailRequired = (bool) ($emailFieldPolicy['visible'] ?? true)
            && (bool) ($emailFieldPolicy['required'] ?? false);

        $existingExternal = $this->indexExistingValues(
            Student::query()->whereNotNull('lrn')->pluck('lrn')
        );
        $existingStudentIds = $this->indexExistingValues(
            Student::query()->whereNotNull('student_id')->pluck('student_id')
        );
        $existingEmails = $this->indexExistingValues(
            Student::query()->whereNotNull('email')->pluck('email')
        );

        $anomalies = [];
        $anomalousRows = [];
        $seenExternal = [];
        $seenEmails = [];
        $seenStudentIds = [];

        $recordAnomaly = static function (int $row, string $name, string $issue, string $severity) use (&$anomalies, &$anomalousRows): void {
            $anomalousRows[$row] = true;
            $anomalies[] = [
                'row' => $row,
                'name' => $name,
                'issue' => $issue,
                'severity' => $severity,
            ];
        };

        $profiles = $validated['profiles'];

        foreach ($profiles as $index => $profile) {
            $rowNumber = $index + 1;
            $firstName = mb_trim((string) $profile['first_name']);
            $lastName = mb_trim((string) $profile['last_name']);
            $fullName = mb_trim("{$firstName} {$lastName}");

            $studentType = filled($profile['student_type'] ?? null)
                ? mb_trim((string) $profile['student_type'])
                : $defaultStudentType;

            $externalPolicy = $configService->externalFor($studentType);
            $externalLabel = filled($externalPolicy['label'] ?? null)
                ? mb_trim((string) $externalPolicy['label'])
                : 'External Identifier';

            $externalValue = filled($profile['external_identifier'] ?? null)
                ? mb_trim((string) $profile['external_identifier'])
                : (filled($profile['lrn'] ?? null) ? mb_trim((string) $profile['lrn']) : null);

            if ((bool) ($externalPolicy['enabled'] ?? false)) {
                $externalRequired = (bool) ($externalPolicy['required'] ?? false)
                    || (bool) ($externalPolicy['use_as_student_id'] ?? false);

                if ($externalValue === null) {
                    if ($externalRequired) {
                        $recordAnomaly(
                            $rowNumber,
                            $fullName,
                            "Missing required {$externalLabel} for student type '{$studentType}'.",
                            'Error',
                        );
                    }
                } else {
                    $formatError = $this->validateExternalIdentifierFormat(
                        $externalValue,
                        $externalLabel,
                        $externalPolicy,
                    );

                    if ($formatError !== null) {
                        $recordAnomaly($rowNumber, $fullName, $formatError, 'Error');
                    } else {
                        $normalizedExternal = mb_strtolower($externalValue);
                        $conflictsWithStudentId = (bool) ($externalPolicy['use_as_student_id'] ?? false)
                            && isset($existingStudentIds[$normalizedExternal]);

                        if (isset($existingExternal[$normalizedExternal]) || $conflictsWithStudentId) {
                            $recordAnomaly(
                                $rowNumber,
                                $fullName,
                                "Duplicate {$externalLabel} '{$externalValue}' already exists in active student directory.",
                                'Critical Duplicate',
                            );
                        } elseif (isset($seenExternal[$normalizedExternal])) {
                            $recordAnomaly(
                                $rowNumber,
                                $fullName,
                                "Duplicate {$externalLabel} '{$externalValue}' also appears on row {$seenExternal[$normalizedExternal]} of this import batch.",
                                'Critical Duplicate',
                            );
                        } else {
                            $seenExternal[$normalizedExternal] = $rowNumber;
                        }
                    }
                }
            }

            $email = filled($profile['email'] ?? null)
                ? mb_trim((string) $profile['email'])
                : null;

            if ($email === null) {
                if ($emailRequired) {
                    $recordAnomaly(
                        $rowNumber,
                        $fullName,
                        'Missing required email address.',
                        'Error',
                    );
                }
            } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $recordAnomaly(
                    $rowNumber,
                    $fullName,
                    "Invalid email address '{$email}'.",
                    'Error',
                );
            } else {
                $normalizedEmail = mb_strtolower($email);

                if (isset($existingEmails[$normalizedEmail])) {
                    $recordAnomaly(
                        $rowNumber,
                        $fullName,
                        "Duplicate email address '{$email}' already exists in active student directory.",
                        'Critical Duplicate',
                    );
                } elseif (isset($seenEmails[$normalizedEmail])) {
                    $recordAnomaly(
                        $rowNumber,
                        $fullName,
                        "Duplicate email address '{$email}' also appears on row {$seenEmails[$normalizedEmail]} of this import batch.",
                        'Critical Duplicate',
                    );
                } else {
                    $seenEmails[$normalizedEmail] = $rowNumber;
                }
            }

            $studentId = filled($profile['student_id'] ?? null)
                ? mb_trim((string) $profile['student_id'])
                : null;

            if ($studentId !== null) {
                $normalizedStudentId = mb_strtolower($studentId);

                if (isset($existingStudentIds[$normalizedStudentId])) {
                    $recordAnomaly(
                        $rowNumber,
                        $fullName,
                        "Duplicate Student ID '{$studentId}' already exists in active student directory.",
                        'Critical Duplicate',
                    );
                } elseif (isset($seenStudentIds[$normalizedStudentId])) {
                    $recordAnomaly(
                        $rowNumber,
                        $fullName,
                        "Duplicate Student ID '{$studentId}' also appears on row {$seenStudentIds[$normalizedStudentId]} of this import batch.",
                        'Critical Duplicate',
                    );
                } else {
                    $seenStudentIds[$normalizedStudentId] = $rowNumber;
                }
            }
        }

        $schoolExternal = is_array($schoolConfig['external_identifier'] ?? null)
            ? $schoolConfig['external_identifier']
            : [];
        $policyLabel = filled($schoolExternal['label'] ?? null)
            ? mb_trim((string) $schoolExternal['label'])
            : 'External Identifier';

        $totalRows = count($profiles);
        $cleanRowsCount = max(0, $totalRows - count($anomalousRows));

        return (string) json_encode([
            'total_rows_audited' => $totalRows,
            'clean_rows_count' => $cleanRowsCount,
            'anomaly_count' => count($anomalies),
            'is_ready_for_import' => $anomalies === [],
            'external_identifier_policy' => [
                'enabled' => (bool) ($schoolExternal['enabled'] ?? false),
                'label' => $policyLabel,
                'format' => (string) ($schoolExternal['format'] ?? 'text'),
                'min_length' => (int) ($schoolExternal['min_length'] ?? 1),
                'max_length' => (int) ($schoolExternal['max_length'] ?? 32),
                'required' => (bool) ($schoolExternal['required'] ?? false),
                'student_types' => is_array($schoolExternal['student_types'] ?? null)
                    ? array_values($schoolExternal['student_types'])
                    : [],
            ],
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
                    'external_identifier' => $s->string(),
                    'lrn' => $s->string(),
                    'student_id' => $s->string(),
                    'email' => $s->string(),
                    'student_type' => $s->string(),
                ])
            )->required(),
        ];
    }

    /**
     * Index non-empty string values by their trimmed lowercase representation.
     *
     * @param  iterable<int|string, mixed>  $values
     * @return array<string, string>
     */
    private function indexExistingValues(iterable $values): array
    {
        $indexed = [];

        foreach ($values as $value) {
            $trimmed = mb_trim((string) $value);
            if ($trimmed === '') {
                continue;
            }

            $indexed[mb_strtolower($trimmed)] = $trimmed;
        }

        return $indexed;
    }

    /**
     * Validate a non-empty external identifier against the school's configured format and length bounds.
     *
     * @param  array<string, mixed>  $policy
     */
    private function validateExternalIdentifierFormat(string $value, string $label, array $policy): ?string
    {
        $format = (string) ($policy['format'] ?? 'text');
        $minLength = (int) ($policy['min_length'] ?? 1);
        $maxLength = (int) ($policy['max_length'] ?? 32);
        $length = mb_strlen($value);

        $lengthValid = $length >= $minLength && $length <= $maxLength;
        $formatValid = match ($format) {
            'digits' => ctype_digit($value),
            'alphanumeric' => preg_match('/\A[A-Za-z0-9]+\z/', $value) === 1,
            default => true,
        };

        if ($lengthValid && $formatValid) {
            return null;
        }

        $unit = match ($format) {
            'digits' => 'numeric digits',
            'alphanumeric' => 'alphanumeric characters',
            default => 'characters',
        };

        $requirement = $minLength === $maxLength
            ? "exactly {$minLength} {$unit}"
            : "between {$minLength} and {$maxLength} {$unit}";

        return "Invalid {$label} '{$value}'. {$label} must be {$requirement}.";
    }
}
