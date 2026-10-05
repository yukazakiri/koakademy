<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentType;
use App\Models\Faculty;
use App\Models\IdSequence;
use App\Models\Student;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;

final class IdentifierGenerator
{
    public const string Student = 'student';

    public const string Staff = 'staff';

    public function preview(string $key): string
    {
        $sequence = $this->sequenceFor($key);

        return $this->format((int) $sequence->next_number, $sequence->padding);
    }

    public function generate(string $key): string
    {
        return DB::transaction(function () use ($key): string {
            $sequence = $this->sequenceFor($key, lock: true);
            $identifier = $this->format((int) $sequence->next_number, $sequence->padding);

            $sequence->forceFill([
                'next_number' => (int) $sequence->next_number + (int) $sequence->increment_by,
            ])->save();

            return $identifier;
        });
    }

    public function previewStudentId(?StudentType $type = null): int
    {
        $sequence = $this->sequenceFor(self::Student);

        return $this->resolveFormattedStudentId((int) $sequence->next_number, $sequence, $type);
    }

    public function generateStudentId(?StudentType $type = null): int
    {
        return DB::transaction(function () use ($type): int {
            $sequence = $this->sequenceFor(self::Student, lock: true);
            $studentId = $this->resolveFormattedStudentId((int) $sequence->next_number, $sequence, $type);

            $sequence->forceFill([
                'next_number' => (int) $sequence->next_number + (int) $sequence->increment_by,
            ])->save();

            return $studentId;
        });
    }

    public function previewStaffId(): string
    {
        return $this->preview(self::Staff);
    }

    public function generateStaffId(): string
    {
        return $this->generate(self::Staff);
    }

    public function resolvePrefix(IdSequence $sequence, ?StudentType $studentType = null): string
    {
        $mode = $sequence->prefix_mode ?? 'none';

        return match ($mode) {
            'static' => (string) ($sequence->prefix_value ?? ''),
            'year' => (string) date('Y'),
            'by_type' => $this->resolveTypePrefix($sequence, $studentType),
            default => '',
        };
    }

    public function resolveFormattedStudentId(int $nextNumber, IdSequence $sequence, ?StudentType $studentType = null): int
    {
        $prefix = $this->resolvePrefix($sequence, $studentType);
        $formatted = $this->format($nextNumber, $sequence->padding);

        if ($prefix !== '' && ! str_starts_with($formatted, $prefix)) {
            $combined = $prefix.$formatted;
            if (ctype_digit($combined) && (float) $combined <= 2147483647) {
                return (int) $combined;
            }
        }

        return (int) $formatted;
    }

    /**
     * Get dynamic validation rules for student ID based on configuration.
     *
     * @return array<int, string|Unique|Closure>
     */
    public function getStudentIdValidationRules(
        ?StudentType $studentType = null,
        ?int $ignoreId = null,
        bool $required = true
    ): array {
        if ($studentType === StudentType::SeniorHighSchool) {
            return ['nullable', 'string', 'max:20'];
        }

        $sequence = $this->sequenceFor(self::Student);
        $rules = [];

        if ($required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $rules[] = 'numeric';

        // Length validation
        if ($sequence->enforce_length && $sequence->exact_length) {
            $rules[] = 'digits:'.$sequence->exact_length;
        } else {
            $min = $sequence->min_length ?? 4;
            $max = $sequence->max_length ?? 12;
            $rules[] = "digits_between:{$min},{$max}";
        }

        // Uniqueness validation
        $uniqueRule = Rule::unique('students', 'student_id');
        if ($ignoreId !== null) {
            $uniqueRule = $uniqueRule->ignore($ignoreId);
        }
        $rules[] = $uniqueRule;

        // Prefix validation if enforced
        if ($sequence->enforce_prefix) {
            $prefix = $this->resolvePrefix($sequence, $studentType);
            if ($prefix !== '') {
                $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($prefix, $studentType): void {
                    $valueStr = (string) $value;
                    if (! str_starts_with($valueStr, $prefix)) {
                        $typeLabel = $studentType?->getLabel() ?? 'Student';
                        $fail("The student ID must start with {$prefix} for {$typeLabel}.");
                    }
                };
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function configuration(): array
    {
        return collect([self::Student, self::Staff])
            ->mapWithKeys(function (string $key): array {
                $sequence = $this->sequenceFor($key);

                $config = [
                    'key' => $sequence->key,
                    'label' => $sequence->label,
                    'start_number' => (int) $sequence->start_number,
                    'next_number' => (int) $sequence->next_number,
                    'increment_by' => (int) $sequence->increment_by,
                    'padding' => $sequence->padding === null ? null : (int) $sequence->padding,
                    'preview' => $this->format((int) $sequence->next_number, $sequence->padding),
                ];

                if ($key === self::Student) {
                    $config['prefix_mode'] = $sequence->prefix_mode ?? 'none';
                    $config['prefix_value'] = $sequence->prefix_value;
                    $config['enforce_prefix'] = (bool) ($sequence->enforce_prefix ?? false);
                    $config['enforce_length'] = (bool) ($sequence->enforce_length ?? false);
                    $config['exact_length'] = $sequence->exact_length ? (int) $sequence->exact_length : null;
                    $config['min_length'] = $sequence->min_length ? (int) $sequence->min_length : 4;
                    $config['max_length'] = $sequence->max_length ? (int) $sequence->max_length : 12;
                    $config['type_prefixes'] = $sequence->type_prefixes ?? [
                        'college' => '2',
                        'tesda' => '2',
                        'dhrt' => '2',
                        'shs' => '3',
                    ];
                    $config['type_previews'] = [
                        'college' => (string) $this->previewStudentId(StudentType::College),
                        'tesda' => (string) $this->previewStudentId(StudentType::TESDA),
                        'dhrt' => (string) $this->previewStudentId(StudentType::DHRT),
                    ];
                }

                return [$key => $config];
            })
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $sequences
     */
    public function updateConfiguration(array $sequences): void
    {
        DB::transaction(function () use ($sequences): void {
            foreach ([self::Student, self::Staff] as $key) {
                if (! isset($sequences[$key])) {
                    continue;
                }

                $sequence = $this->sequenceFor($key, lock: true);
                $data = $sequences[$key];

                $fillData = [
                    'start_number' => (int) $data['start_number'],
                    'next_number' => (int) $data['next_number'],
                    'increment_by' => (int) $data['increment_by'],
                    'padding' => isset($data['padding']) && $data['padding'] !== null && $data['padding'] !== ''
                        ? (int) $data['padding']
                        : null,
                ];

                if (array_key_exists('prefix_mode', $data)) {
                    $fillData['prefix_mode'] = (string) $data['prefix_mode'];
                }
                if (array_key_exists('prefix_value', $data)) {
                    $fillData['prefix_value'] = $data['prefix_value'] !== null && $data['prefix_value'] !== ''
                        ? (string) $data['prefix_value']
                        : null;
                }
                if (array_key_exists('enforce_prefix', $data)) {
                    $fillData['enforce_prefix'] = (bool) $data['enforce_prefix'];
                }
                if (array_key_exists('enforce_length', $data)) {
                    $fillData['enforce_length'] = (bool) $data['enforce_length'];
                }
                if (array_key_exists('exact_length', $data)) {
                    $fillData['exact_length'] = isset($data['exact_length']) && $data['exact_length'] !== null && $data['exact_length'] !== ''
                        ? (int) $data['exact_length']
                        : null;
                }
                if (array_key_exists('min_length', $data)) {
                    $fillData['min_length'] = isset($data['min_length']) && $data['min_length'] !== null && $data['min_length'] !== ''
                        ? (int) $data['min_length']
                        : 4;
                }
                if (array_key_exists('max_length', $data)) {
                    $fillData['max_length'] = isset($data['max_length']) && $data['max_length'] !== null && $data['max_length'] !== ''
                        ? (int) $data['max_length']
                        : 12;
                }
                if (array_key_exists('type_prefixes', $data) && is_array($data['type_prefixes'])) {
                    $fillData['type_prefixes'] = $data['type_prefixes'];
                }

                $sequence->forceFill($fillData)->save();
            }
        });
    }

    public function sequenceFor(string $key, bool $lock = false): IdSequence
    {
        $query = IdSequence::query()->where('key', $key);

        if ($lock) {
            $query->lockForUpdate();
        }

        $sequence = $query->first();

        if ($sequence instanceof IdSequence) {
            return $this->adaptToExistingRecords($sequence);
        }

        return IdSequence::query()->create($this->defaultsFor($key));
    }

    private function resolveTypePrefix(IdSequence $sequence, ?StudentType $studentType = null): string
    {
        if (! $studentType instanceof StudentType) {
            return (string) ($sequence->prefix_value ?? '2');
        }

        $typePrefixes = is_array($sequence->type_prefixes) ? $sequence->type_prefixes : [];

        if (isset($typePrefixes[$studentType->value]) && $typePrefixes[$studentType->value] !== '') {
            return (string) $typePrefixes[$studentType->value];
        }

        return $studentType->getIdPrefix();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsFor(string $key): array
    {
        return match ($key) {
            self::Student => $this->studentDefaults(),
            self::Staff => $this->staffDefaults(),
            default => throw new InvalidArgumentException("Unknown ID sequence [{$key}]."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function studentDefaults(): array
    {
        $startNumber = 200000;
        $nextNumber = max((int) $this->latestCreatedGeneratedStudentId() + 1, $startNumber);

        return [
            'key' => self::Student,
            'label' => 'Student IDs',
            'start_number' => $startNumber,
            'next_number' => $nextNumber,
            'increment_by' => 1,
            'padding' => 6,
            'prefix_mode' => 'none',
            'prefix_value' => null,
            'enforce_prefix' => false,
            'enforce_length' => false,
            'exact_length' => 6,
            'min_length' => 4,
            'max_length' => 12,
            'type_prefixes' => [
                'college' => '2',
                'tesda' => '2',
                'dhrt' => '2',
                'shs' => '3',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffDefaults(): array
    {
        $startNumber = 800000;
        $latestNumericFacultyId = $this->latestNumericFacultyId();

        $nextNumber = max((int) $latestNumericFacultyId + 1, $startNumber);

        return [
            'key' => self::Staff,
            'label' => 'Staff IDs',
            'start_number' => $startNumber,
            'next_number' => $nextNumber,
            'increment_by' => 1,
            'padding' => 6,
        ];
    }

    private function adaptToExistingRecords(IdSequence $sequence): IdSequence
    {
        $latestStudentId = $sequence->key === self::Student
            ? $this->latestCreatedGeneratedStudentId()
            : null;

        $minimumNextNumber = match ($sequence->key) {
            self::Student => $latestStudentId === null
                ? (int) $sequence->start_number
                : $latestStudentId + 1,
            self::Staff => (int) $this->latestNumericFacultyId() + 1,
            default => (int) $sequence->next_number,
        };

        if ($sequence->key === self::Student && $this->shouldRepairStudentSequence((int) $sequence->next_number, $minimumNextNumber, $sequence->padding, (int) $sequence->start_number)) {
            $sequence->forceFill([
                'next_number' => max($minimumNextNumber, (int) $sequence->start_number),
            ])->save();

            return $sequence->refresh();
        }

        if ($minimumNextNumber <= (int) $sequence->next_number) {
            return $sequence;
        }

        $sequence->forceFill([
            'next_number' => $minimumNextNumber,
        ])->save();

        return $sequence->refresh();
    }

    private function latestNumericFacultyId(): ?int
    {
        return Faculty::query()
            ->whereNotNull('faculty_id_number')
            ->pluck('faculty_id_number')
            ->filter(fn (string $facultyIdNumber): bool => ctype_digit($facultyIdNumber))
            ->map(fn (string $facultyIdNumber): int => (int) $facultyIdNumber)
            ->max();
    }

    private function latestCreatedGeneratedStudentId(): ?int
    {
        return Student::query()
            ->withTrashed()
            ->where('student_type', '!=', StudentType::SeniorHighSchool->value)
            ->whereNotNull('student_id')
            ->latest('created_at')
            ->latest('id')
            ->value('student_id');
    }

    private function shouldRepairStudentSequence(int $nextNumber, int $expectedNextNumber, ?int $padding, int $startNumber = 200000): bool
    {
        // Polluted by 12-digit SHS LRN
        if (mb_strlen((string) $nextNumber) >= 12) {
            return true;
        }

        if ($padding !== null && mb_strlen((string) $nextNumber) > $padding) {
            return true;
        }

        // If nextNumber is aligned with user's configured start_number, do not repair
        if ($nextNumber >= $startNumber && abs($nextNumber - $startNumber) < 50000) {
            return false;
        }

        $distanceFromLatestPattern = abs($nextNumber - $expectedNextNumber);

        return $distanceFromLatestPattern > 100000;
    }

    private function format(int $number, ?int $padding): string
    {
        if ($padding === null || $padding < 1) {
            return (string) $number;
        }

        return mb_str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }
}
