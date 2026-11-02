<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentType;
use App\Models\Faculty;
use App\Models\IdSequence;
use App\Models\Student;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class IdentifierGenerator
{
    public const string Student = 'student';

    public const string Staff = 'staff';

    public function previewStudentId(?StudentType $type = null, ?int $schoolId = null): string
    {
        $sequence = $this->sequenceFor(self::Student, type: $type, schoolId: $schoolId);

        return $this->nextAvailable($sequence, $type, $schoolId)[1];
    }

    public function generateStudentId(?StudentType $type = null, ?int $schoolId = null): string
    {
        return DB::transaction(function () use ($type, $schoolId): string {
            $sequence = $this->sequenceFor(self::Student, lock: true, type: $type, schoolId: $schoolId);
            app(StudentConfigurationService::class)->forget($schoolId);
            if ($type !== null && app(StudentConfigurationService::class)->usesExternal($type->value, $schoolId)) {
                throw ValidationException::withMessages(['student_id' => 'This category uses its external identifier as the student number.']);
            }
            if ($sequence->assignment_mode === 'manual') {
                throw ValidationException::withMessages(['student_id' => 'This school requires a manually assigned identifier.']);
            }
            [$counter, $identifier] = $this->nextAvailable($sequence, $type, $schoolId);
            $this->validateFormat($identifier, $sequence, $type);
            $sequence->forceFill(['next_number' => $counter + $sequence->increment_by])->save();

            return $identifier;
        });
    }

    /** Shared by non-HTTP entry points such as approved AI/MCP tools. */
    public function assignForNewStudent(array $attributes, ?int $schoolId = null): string
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn (): string => $this->assignForNewStudent($attributes, $schoolId));
        }
        $this->sequenceFor(self::Student, lock: true, schoolId: $schoolId);
        $configuration = app(StudentConfigurationService::class);
        $configuration->forget($schoolId);
        $configuration->assertRequiredFieldsSupported(['email'], $schoolId);
        $type = StudentType::from($attributes['student_type'] ?? $configuration->get($schoolId)['default_type']);
        foreach (array_keys($attributes) as $field) {
            if (! $configuration->fieldIsVisible($field, $schoolId)) {
                throw ValidationException::withMessages([$field => 'This field is disabled by the school configuration.']);
            }
        }
        $rules = $configuration->rules([
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
        ], $type->value, schoolId: $schoolId);
        Validator::make($attributes, $rules)->validate();

        return $configuration->usesExternal($type->value, $schoolId)
            ? (string) $attributes['lrn'] : $this->assign($type, null, 'automatic', $schoolId);
    }

    /** Recheck an external number after acquiring the settings/sequence lock. */
    public function claimExternalIdentifier(StudentType $type, string $value, ?int $schoolId = null): string
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn (): string => $this->claimExternalIdentifier($type, $value, $schoolId));
        }
        $this->sequenceFor(self::Student, lock: true, schoolId: $schoolId);
        $configuration = app(StudentConfigurationService::class);
        $configuration->forget($schoolId);
        if (! $configuration->usesExternal($type->value, $schoolId)) {
            throw ValidationException::withMessages(['lrn' => 'Identifier settings changed. Reload the form before saving.']);
        }
        $rules = $configuration->rules([], $type->value, schoolId: $schoolId)['lrn'];
        Validator::make(['lrn' => $value], ['lrn' => $rules])->validate();

        return $value;
    }

    /** Call inside the transaction that creates the student. Never trust a preview. */
    public function assign(StudentType $type, ?string $value, string $mode, ?int $schoolId = null): string
    {
        return DB::transaction(function () use ($type, $value, $mode, $schoolId): string {
            $sequence = $this->sequenceFor(self::Student, lock: true, type: $type, schoolId: $schoolId);
            app(StudentConfigurationService::class)->forget($schoolId);
            if (app(StudentConfigurationService::class)->usesExternal($type->value, $schoolId)) {
                throw ValidationException::withMessages(['student_id' => 'This category uses its external identifier as the student number.']);
            }
            if ($mode === 'automatic') {
                if ($sequence->assignment_mode === 'manual') {
                    throw ValidationException::withMessages(['student_id' => 'This school requires a manually assigned identifier.']);
                }

                return $this->generateStudentId($type, $schoolId);
            }
            if ($sequence->assignment_mode === 'automatic') {
                throw ValidationException::withMessages(['student_id' => 'Manual identifier overrides are disabled for this school.']);
            }
            Validator::make(['student_id' => $value], ['student_id' => $this->getStudentIdValidationRules($type, schoolId: $schoolId)])->validate();

            return (string) $value;
        });
    }

    public function preview(string $key): string
    {
        if ($key === self::Student) {
            return $this->previewStudentId();
        }
        $sequence = $this->sequenceFor($key);

        return $this->format($sequence->next_number, $sequence->padding);
    }

    public function generate(string $key): string
    {
        if ($key === self::Student) {
            return $this->generateStudentId();
        }

        return DB::transaction(function () use ($key): string {
            $sequence = $this->sequenceFor($key, lock: true);
            $number = $sequence->next_number;
            while (Faculty::withoutSchoolScope()->where('faculty_id_number', $this->format($number, $sequence->padding))->exists()) {
                $number += $sequence->increment_by;
            }
            $sequence->forceFill(['next_number' => $number + $sequence->increment_by])->save();

            return $this->format($number, $sequence->padding);
        });
    }

    public function studentPolicy(?StudentType $type = null, ?int $schoolId = null): array
    {
        return $this->sequenceFor(self::Student, type: $type, schoolId: $schoolId)->only([
            'display_label', 'assignment_mode', 'character_set', 'legacy_format',
        ]);
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
        return match ($sequence->prefix_mode) {
            'static' => (string) $sequence->prefix_value,
            'year' => now()->format('Y'),
            'by_type' => (string) (($sequence->type_prefixes ?? [])[$studentType?->value ?? 'college'] ?? ''),
            default => '',
        };
    }

    public function resolveFormattedStudentId(int $nextNumber, IdSequence $sequence, ?StudentType $studentType = null): string
    {
        $prefix = $this->resolvePrefix($sequence, $studentType);
        $number = $this->format($nextNumber, $sequence->padding);
        if ($sequence->legacy_format) {
            // Explicit compatibility mode only. New policies always prepend the prefix.
            $combined = $prefix !== '' && ! str_starts_with($number, $prefix) ? $prefix.$number : $number;

            return (string) (int) (ctype_digit($combined) && (float) $combined <= 2147483647 ? $combined : $number);
        }

        return $prefix.$number;
    }

    public function getStudentIdValidationRules(?StudentType $studentType = null, ?int $ignoreId = null, bool $required = true, ?int $schoolId = null): array
    {
        $schoolId ??= app(TenantContext::class)->getCurrentSchoolId();
        $unique = Rule::unique('students', 'student_id')->where('school_id', $schoolId)->ignore($ignoreId);
        if ($ignoreId !== null) {
            $existing = Student::withoutSchoolScope()->withTrashed()->where('school_id', $schoolId)->find($ignoreId);
            // Existing identifiers remain valid after a policy change; uniqueness is still checked.
            if ($existing !== null) {
                $sequence = $this->sequenceFor(self::Student, type: $studentType, schoolId: $schoolId);

                return ['bail', $required ? 'required' : 'nullable', function (string $attribute, mixed $value, Closure $fail) use ($existing, $sequence, $studentType, $unique): void {
                    if (! is_string($value) && ! is_int($value)) {
                        $fail('The identifier must be text.');

                        return;
                    }
                    if ((string) $value !== (string) $existing->student_id) {
                        if ($sequence->assignment_mode === 'automatic') {
                            $fail('Manual identifier overrides are disabled for this school.');

                            return;
                        }
                        if (app(StudentConfigurationService::class)->usesExternal($studentType?->value ?? $existing->student_type->value, $existing->school_id)
                            && (string) $existing->student_id === (string) $existing->lrn) {
                            $fail('Change this student number through its external identifier field so both values remain consistent.');

                            return;
                        }
                        $validator = Validator::make(['student_id' => $value], ['student_id' => [...$this->formatRules($sequence, $studentType), $unique]]);
                        if ($validator->fails()) {
                            $fail($validator->errors()->first('student_id'));
                        }
                    }
                }];
            }
        }

        return ['bail', $required ? 'required' : 'nullable', ...$this->formatRules($this->sequenceFor(self::Student, type: $studentType, schoolId: $schoolId), $studentType), $unique];
    }

    public function configuration(): array
    {
        $student = $this->sequenceFor(self::Student);
        $staff = $this->sequenceFor(self::Staff);
        $serialize = fn (IdSequence $sequence): array => array_intersect_key($sequence->toArray(), array_flip(['key', 'label', 'start_number', 'next_number', 'increment_by', 'padding', 'prefix_mode', 'prefix_value', 'enforce_prefix', 'enforce_length', 'exact_length', 'min_length', 'max_length', 'type_prefixes', 'assignment_mode', 'character_set', 'display_label', 'counter_scope', 'legacy_format']));
        $configuration = ['student' => $serialize($student), 'staff' => $serialize($staff)];
        // Settings must remain reachable even when a sequence needs repair.
        try {
            $configuration['student']['preview'] = $this->previewStudentId();
        } catch (ValidationException $exception) {
            $configuration['student']['preview'] = $this->resolveFormattedStudentId($student->next_number, $student);
            $configuration['student']['preview_error'] = $exception->validator->errors()->first();
        }
        $configuration['staff']['preview'] = $this->previewStaffId();
        foreach (StudentType::cases() as $type) {
            try {
                $configuration['student']['type_previews'][$type->value] = $this->previewStudentId($type);
            } catch (ValidationException) {
                $configuration['student']['type_previews'][$type->value] = $this->resolveFormattedStudentId($student->next_number, $student, $type);
            }
            $configuration['student']['type_next_numbers'][$type->value] = $this->sequenceFor(self::Student, type: $type)->next_number;
        }

        return $configuration;
    }

    public function updateConfiguration(array $sequences): void
    {
        DB::transaction(function () use ($sequences): void {
            foreach ([self::Student, self::Staff] as $key) {
                if (! isset($sequences[$key])) {
                    continue;
                }
                $sequence = $this->sequenceFor($key, lock: true);
                $data = $sequences[$key];
                if (isset($data['expected_next_number']) && (int) $data['expected_next_number'] !== $sequence->next_number) {
                    throw ValidationException::withMessages([$key.'.next_number' => 'The sequence advanced while you were editing. Reload the settings before saving.']);
                }
                $sequence->fill(array_intersect_key($data, array_flip(array_diff($sequence->getFillable(), ['scope', 'key', 'label']))));
                if ($key === self::Student && $sequence->assignment_mode !== 'manual') {
                    app(StudentConfigurationService::class)->forget();
                    $configuration = app(StudentConfigurationService::class)->get();
                    $prefixes = [];
                    foreach ($configuration['enabled_types'] as $value) {
                        $type = StudentType::from($value);
                        if (app(StudentConfigurationService::class)->usesExternal($value)) {
                            continue;
                        }
                        $prefixes[] = $this->resolvePrefix($sequence, $type);
                        $number = $sequence->counter_scope === 'by_type'
                            ? max($sequence->start_number, (int) ($data['type_next_numbers'][$value] ?? $sequence->start_number))
                            : $sequence->next_number;
                        $validator = Validator::make(['identifier' => $this->resolveFormattedStudentId($number, $sequence, $type)], ['identifier' => $this->formatRules($sequence, $type)]);
                        if ($validator->fails()) {
                            throw ValidationException::withMessages(['student.next_number' => $validator->errors()->first('identifier')]);
                        }
                    }
                    if ($sequence->counter_scope === 'by_type' && count(array_unique($prefixes)) !== count($prefixes)) {
                        throw ValidationException::withMessages(['student.counter_scope' => 'Separate counters require distinct category prefixes to avoid overlapping identifiers.']);
                    }
                }
                $sequence->save();
                if ($key === self::Student && $sequence->counter_scope === 'shared') {
                    // Existing per-category rows keep their positions, but their
                    // formatting must follow policy while shared mode is active.
                    foreach (StudentType::cases() as $type) {
                        $counter = IdSequence::query()->where('scope', $sequence->scope)->where('key', self::Student.':'.$type->value)->lockForUpdate()->first();
                        if ($counter === null) {
                            continue;
                        }
                        $settings = $sequence->getAttributes();
                        $settings['type_prefixes'] = $sequence->type_prefixes;
                        unset($settings['id'], $settings['key'], $settings['scope'], $settings['created_at'], $settings['updated_at'], $settings['next_number']);
                        $counter->forceFill($settings)->save();
                    }
                }
                if ($key === self::Student && $sequence->counter_scope === 'by_type') {
                    foreach (StudentType::cases() as $type) {
                        $counter = $this->sequenceFor(self::Student, lock: true, type: $type);
                        $requested = $data['type_next_numbers'][$type->value] ?? $counter->next_number;
                        $expected = $data['expected_type_next_numbers'][$type->value] ?? null;
                        if ($expected !== null && (int) $expected !== $counter->next_number) {
                            throw ValidationException::withMessages(['student.type_next_numbers.'.$type->value => 'This category counter advanced. Reload before saving.']);
                        }
                        $settings = $sequence->getAttributes();
                        $settings['type_prefixes'] = $sequence->type_prefixes;
                        unset($settings['id'], $settings['key'], $settings['scope'], $settings['created_at'], $settings['updated_at'], $settings['next_number']);
                        $settings['next_number'] = max($sequence->start_number, (int) $requested);
                        $counter->forceFill($settings)->save();
                    }
                }
            }
        });
    }

    public function sequenceFor(string $key, bool $lock = false, ?StudentType $type = null, ?int $schoolId = null): IdSequence
    {
        if (! in_array($key, [self::Student, self::Staff], true)) {
            throw new InvalidArgumentException("Unknown ID sequence [{$key}].");
        }
        // Staff numbers are still installation-unique in faculty validation and
        // signup. Keep their shared installation counter until that contract changes.
        $scope = $key === self::Staff ? 'installation' : $this->scope($schoolId);
        $base = IdSequence::query()->where('scope', $scope)->where('key', $key)->first();
        $base ??= IdSequence::query()->createOrFirst(['scope' => $scope, 'key' => $key], $this->defaults($key, $schoolId));
        if ($lock && $key === self::Student) {
            // Lock base before category counters so settings and issuance serialize.
            $base = IdSequence::query()->whereKey($base->id)->lockForUpdate()->firstOrFail();
        }
        if ($key === self::Student && $type !== null && $base->counter_scope === 'by_type') {
            $key .= ':'.$type->value;
            $attributes = $base->getAttributes();
            $attributes['type_prefixes'] = $base->type_prefixes;
            unset($attributes['id'], $attributes['scope'], $attributes['key'], $attributes['created_at'], $attributes['updated_at']);
            $attributes['next_number'] = $base->start_number;
            if (! IdSequence::query()->where('scope', $scope)->where('key', $key)->exists()) {
                IdSequence::query()->createOrFirst(['scope' => $scope, 'key' => $key], $attributes);
            }
        }

        return IdSequence::query()->where('scope', $scope)->where('key', $key)->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
    }

    public function assertPolicyFormat(IdSequence $sequence, StudentType $type, int $counter): void
    {
        $this->validateFormat($this->resolveFormattedStudentId($counter, $sequence, $type), $sequence, $type);
    }

    private function scope(?int $schoolId = null): string
    {
        return StudentConfigurationService::scope($schoolId ?? app(TenantContext::class)->getCurrentSchoolId());
    }

    private function formatRules(IdSequence $sequence, ?StudentType $type): array
    {
        $rules = ['bail', 'regex:'.($sequence->character_set === 'numeric' ? '/\A[0-9]+\z/' : '/\A[A-Za-z0-9_-]+\z/')];
        $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($sequence, $type): void {
            $length = mb_strlen((string) $value);
            $minimum = $sequence->enforce_length ? (int) ($sequence->exact_length ?? 6) : (int) ($sequence->min_length ?? 1);
            $maximum = $sequence->enforce_length ? (int) ($sequence->exact_length ?? 6) : (int) ($sequence->max_length ?? 64);
            if ($length < $minimum || $length > $maximum) {
                $fail("The identifier must contain {$minimum}–{$maximum} characters.");
            }
            $prefix = $this->resolvePrefix($sequence, $type);
            if ($sequence->enforce_prefix && $prefix !== '' && ! str_starts_with((string) $value, $prefix)) {
                $fail("The identifier must start with {$prefix}.");
            }
        };

        return $rules;
    }

    private function validateFormat(string $identifier, IdSequence $sequence, ?StudentType $type): void
    {
        Validator::make(['student_id' => $identifier], ['student_id' => $this->formatRules($sequence, $type)])->validate();
    }

    private function nextAvailable(IdSequence $sequence, ?StudentType $type, ?int $schoolId): array
    {
        $schoolId ??= app(TenantContext::class)->getCurrentSchoolId();
        $number = max($sequence->start_number, $sequence->next_number);
        for ($attempt = 0; $attempt < 10000; $attempt++) {
            if ($number > 999999999999) {
                break;
            }
            $identifier = $this->resolveFormattedStudentId($number, $sequence, $type);
            if (! Student::withoutSchoolScope()->withTrashed()->where('school_id', $schoolId)->where('student_id', $identifier)->exists()) {
                return [$number, $identifier];
            }
            $number += $sequence->increment_by;
        }

        throw ValidationException::withMessages(['student_id' => 'The identifier sequence is exhausted or overlaps existing records. Update its next number.']);
    }

    private function defaults(string $key, ?int $schoolId): array
    {
        $legacy = app(StudentConfigurationService::class)->get($schoolId)['preset'] === 'legacy_ph';
        $start = $legacy ? ($key === self::Student ? 200000 : 800000) : 1;
        $next = $start;
        if ($legacy) {
            $query = $key === self::Student
                ? Student::withoutSchoolScope()->withTrashed()->where('student_type', '!=', 'shs')
                : Faculty::withoutSchoolScope();
            $column = $key === self::Student ? 'student_id' : 'faculty_id_number';
            $last = $key === self::Student
                ? $query->where('school_id', $schoolId ?? app(TenantContext::class)->getCurrentSchoolId())->orderByDesc('created_at')->orderByDesc('id')->value($column)
                : $query->pluck($column)->filter(fn ($value): bool => ctype_digit((string) $value))->map(fn ($value): int => (int) $value)->max();
            if ($last !== null && ctype_digit((string) $last)) {
                $next = max($start, (int) $last + 1);
            }
        }

        return [
            'label' => $key === self::Student ? 'Student IDs' : 'Staff IDs',
            'start_number' => $start, 'next_number' => $next, 'increment_by' => 1, 'padding' => 6,
            'prefix_mode' => 'none', 'prefix_value' => null, 'enforce_prefix' => false,
            'enforce_length' => false, 'exact_length' => 6, 'min_length' => $legacy ? 4 : 1, 'max_length' => $legacy ? 12 : 64,
            'type_prefixes' => $legacy ? ['college' => '2', 'tesda' => '2', 'dhrt' => '2', 'shs' => '3'] : [],
            'assignment_mode' => 'hybrid', 'character_set' => 'numeric', 'display_label' => 'Student ID',
            'counter_scope' => 'shared', 'legacy_format' => $legacy,
        ];
    }

    private function format(int $number, ?int $padding): string
    {
        return mb_str_pad((string) $number, $padding ?? 0, '0', STR_PAD_LEFT);
    }
}
