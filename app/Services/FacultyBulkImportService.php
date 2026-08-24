<?php

declare(strict_types=1);

namespace App\Services;

use App\Imports\FacultyBulkImportRowsImport;
use App\Models\Faculty;
use App\Models\FacultyBulkImport;
use App\Models\FacultyBulkImportRow;
use App\Models\FacultyCustomFieldDefinition;
use App\Models\FacultyCustomFieldValue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Process;
use Throwable;

final readonly class FacultyBulkImportService
{
    private const MAX_ROWS = 10000;

    /** @var list<string> */
    private const STANDARD_FIELDS = [
        'faculty_id_number', 'first_name', 'middle_name', 'last_name', 'email', 'department', 'position', 'status',
        'gender', 'birth_date', 'age', 'phone_number', 'office_hours', 'address_line1', 'biography', 'education',
        'courses_taught', 'date_employed',
    ];

    public function __construct(
        private TenantContext $tenantContext,
        private FacultyCustomFieldDefinitionService $definitions,
    ) {}

    public function stage(User $actor, UploadedFile $file): FacultyBulkImport
    {
        $schoolId = $this->schoolId();
        $activeDefinitions = $this->definitions->activeForSchool($schoolId);
        $rows = $this->readRows($file, $activeDefinitions->all());
        $facultyLookups = $this->facultyLookups($rows, $schoolId);
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            throw ValidationException::withMessages(['file' => 'The upload checksum could not be calculated.']);
        }

        return DB::transaction(function () use ($actor, $file, $schoolId, $activeDefinitions, $rows, $facultyLookups, $checksum): FacultyBulkImport {
            $import = FacultyBulkImport::query()->create([
                'public_id' => (string) Str::uuid(),
                'school_id' => $schoolId,
                'uploaded_by_user_id' => $actor->id,
                'original_filename' => $file->getClientOriginalName(),
                'source_type' => $this->sourceType($file),
                'checksum' => $checksum,
                'status' => 'review',
            ]);
            $seenIds = [];
            foreach ($rows as $offset => $input) {
                $import->rows()->create($this->stageRow(
                    $input,
                    $offset + 2,
                    $schoolId,
                    $activeDefinitions->all(),
                    $facultyLookups['by_faculty_id_number'],
                    $facultyLookups['by_email'],
                    $seenIds,
                ));
            }
            $this->refreshCounts($import);

            return $import->refresh();
        });
    }

    /** @param list<int> $rowIds */
    public function confirm(FacultyBulkImport $import, User $actor, array $rowIds): FacultyBulkImport
    {
        $schoolId = $this->schoolId();
        if ($import->school_id !== $schoolId || $import->uploaded_by_user_id !== $actor->id) {
            abort(404);
        }

        return DB::transaction(function () use ($import, $actor, $rowIds, $schoolId): FacultyBulkImport {
            $locked = FacultyBulkImport::query()->whereKey($import->id)->where('school_id', $schoolId)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'review') {
                throw ValidationException::withMessages([
                    'import' => 'This faculty import has already been confirmed.',
                ]);
            }
            $rows = $locked->rows()->whereIn('id', $rowIds)->where('status', 'ready')->orderBy('row_number')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['row_ids' => 'Select at least one ready row to import.']);
            }
            $this->authorizeRows($actor, $rows);
            $definitions = $this->definitions->allForSchool($schoolId)->keyBy('key');
            $this->validateRowsAgainstDefinitions($rows, $definitions);
            $locked->rows()
                ->where('status', 'ready')
                ->whereNotIn('id', $rows->pluck('id'))
                ->update([
                    'status' => 'skipped',
                    'result' => ['message' => 'Not selected for confirmation.'],
                    'updated_at' => now(),
                ]);
            foreach ($rows as $row) {
                $this->apply($row, $schoolId, $definitions);
            }
            $locked->forceFill([
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at' => now(),
                'status' => 'completed',
            ])->save();
            $this->refreshCounts($locked);

            return $locked->refresh();
        });
    }

    /** @return array<string, mixed> */
    public function serialize(FacultyBulkImport $import): array
    {
        $definitions = $this->definitions->allForSchool($import->school_id)->keyBy('key');

        return [
            'id' => $import->public_id,
            'status' => $import->status,
            'filename' => $import->original_filename,
            'summary' => [
                'ready_rows' => $import->ready_count,
                'invalid_rows' => $import->invalid_count,
                'applied_rows' => $import->applied_count,
                'skipped_rows' => $import->skipped_count,
            ],
            'rows' => $import->rows()->orderBy('row_number')->get()->map(function (FacultyBulkImportRow $row) use ($definitions): array {
                $payload = $row->payload ?? [];
                $fields = collect(Arr::get($payload, 'custom_fields', []))->map(function (mixed $value, string $key) use ($definitions): ?array {
                    $definition = $definitions->get($key);
                    if (! $definition instanceof FacultyCustomFieldDefinition) {
                        return null;
                    }

                    return [
                        'label' => $definition->label,
                        'value' => $value === null || $value === '' ? null : ($definition->is_sensitive ? '••••' : (string) $value),
                        'masked' => $definition->is_sensitive,
                    ];
                })->filter()->values()->all();

                return [
                    'id' => (string) $row->id,
                    'source_row' => $row->row_number,
                    'faculty_id_number' => $row->faculty_id_number,
                    'name' => $row->name,
                    'status' => $row->status,
                    'action' => $row->action,
                    'errors' => $row->errors ?? [],
                    'warnings' => $row->warnings ?? [],
                    'fields' => $fields,
                ];
            })->all(),
        ];
    }

    /** @param list<FacultyCustomFieldDefinition> $definitions
     * @param  array<string, list<Faculty>>  $facultiesByFacultyIdNumber
     * @param  array<string, list<Faculty>>  $facultiesByEmail
     * @param  array<string, true>  $seenIds
     * @return array<string, mixed>
     */
    private function stageRow(
        array $input,
        int $rowNumber,
        int $schoolId,
        array $definitions,
        array $facultiesByFacultyIdNumber,
        array $facultiesByEmail,
        array &$seenIds,
    ): array {
        $errors = [];
        $warnings = $input['_warnings'] ?? [];
        $facultyIdNumber = $this->string($input['faculty_id_number'] ?? null);
        $firstName = $this->string($input['first_name'] ?? null);
        $lastName = $this->string($input['last_name'] ?? null);
        $email = $this->string($input['email'] ?? null);
        $key = $facultyIdNumber === null ? null : $this->normalizedFacultyId($facultyIdNumber);

        if ($facultyIdNumber === null) {
            $errors[] = 'Faculty ID Number is required.';
        } elseif (isset($seenIds[$key])) {
            $errors[] = 'Faculty ID Number appears more than once in this upload.';
        } else {
            $seenIds[$key] = true;
        }
        if ($firstName === null || $lastName === null) {
            $errors[] = 'First Name and Last Name are required.';
        }
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email must be valid when supplied.';
        }
        $status = $this->string($input['status'] ?? null);
        if ($status !== null && ! in_array($status, ['active', 'inactive', 'on_leave'], true)) {
            $errors[] = 'Status must be active, inactive, or on_leave.';
        }
        $gender = $this->string($input['gender'] ?? null);
        if ($gender !== null && ! in_array($gender, ['male', 'female', 'other'], true)) {
            $errors[] = 'Gender must be male, female, or other.';
        }
        $age = $this->integer($input['age'] ?? null, 'Age', $errors, 16, 120);
        $birthDate = $this->date($input['birth_date'] ?? null, 'Birth Date', $errors);
        $dateEmployed = $this->date($input['date_employed'] ?? null, 'Date Employed', $errors);
        $existing = $facultyIdNumber === null ? [] : ($facultiesByFacultyIdNumber[$key] ?? []);
        $faculty = count($existing) === 1 ? $existing[0] : null;
        if (count($existing) > 1) {
            $errors[] = 'Faculty ID Number matches multiple existing faculty records.';
        }
        if ($faculty === null && $email === null && $facultyIdNumber !== null) {
            $email = $this->placeholderEmail($facultyIdNumber);
        }
        if ($faculty === null && $status === null) {
            $status = 'active';
        }
        if ($email !== null) {
            $emailOwners = $facultiesByEmail[$this->normalizedEmail($email)] ?? [];
            if (collect($emailOwners)->contains(fn (Faculty $owner): bool => ! $faculty instanceof Faculty || $owner->id !== $faculty->id)) {
                $errors[] = 'Email is already used by another faculty record.';
            }
        }

        $customFields = [];
        foreach ($definitions as $definition) {
            $value = $this->string(Arr::get($input, 'custom_fields.'.$definition->key));
            if ($value !== null) {
                $customFields[$definition->key] = $value;
            }
        }
        $this->validateCustomFields($customFields, $definitions, $errors);

        $payload = [
            'faculty' => [
                'faculty_id_number' => $facultyIdNumber,
                'first_name' => $firstName,
                'middle_name' => $this->string($input['middle_name'] ?? null),
                'last_name' => $lastName,
                'email' => $email,
                'department' => $this->string($input['department'] ?? null),
                'position' => $this->string($input['position'] ?? null),
                'status' => $status,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'age' => $age,
                'phone_number' => $this->string($input['phone_number'] ?? null),
                'office_hours' => $this->string($input['office_hours'] ?? null),
                'address_line1' => $this->string($input['address_line1'] ?? null),
                'biography' => $this->string($input['biography'] ?? null),
                'education' => $this->string($input['education'] ?? null),
                'courses_taught' => $this->string($input['courses_taught'] ?? null),
                'date_employed' => $dateEmployed,
            ],
            'custom_fields' => $customFields,
        ];

        return [
            'school_id' => $schoolId,
            'row_number' => $rowNumber,
            'faculty_id' => $faculty?->id,
            'faculty_id_number' => $facultyIdNumber,
            'name' => mb_trim(implode(' ', array_filter([$firstName, $lastName]))),
            'action' => $faculty instanceof Faculty ? 'update' : 'create',
            'payload' => $payload,
            'errors' => $errors === [] ? null : $errors,
            'warnings' => $warnings === [] ? null : $warnings,
            'status' => $errors === [] ? 'ready' : 'invalid',
        ];
    }

    /** @param Collection<int, FacultyCustomFieldDefinition> $definitions */
    private function apply(FacultyBulkImportRow $row, int $schoolId, Collection $definitions): void
    {
        $payload = $row->payload ?? [];
        $attributes = Arr::get($payload, 'faculty', []);
        if ($row->action === 'create') {
            $conflicts = Faculty::query()
                ->where('school_id', $schoolId)
                ->whereRaw('lower(faculty_id_number) = ?', [$this->normalizedFacultyId((string) $row->faculty_id_number)])
                ->lockForUpdate()
                ->limit(2)
                ->get();
            if ($conflicts->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'import' => "Faculty ID Number {$row->faculty_id_number} was created after this import was staged. Restage the file before confirming.",
                ]);
            }
            $faculty = Faculty::query()->create([
                ...$attributes,
                'school_id' => $schoolId,
                'password' => Hash::make(Str::password(48)),
            ]);
        } elseif ($row->action === 'update') {
            $faculty = Faculty::query()->whereKey($row->faculty_id)->where('school_id', $schoolId)->lockForUpdate()->first();
            if (! $faculty instanceof Faculty) {
                throw ValidationException::withMessages([
                    'import' => "Faculty ID Number {$row->faculty_id_number} changed after this import was staged. Restage the file before confirming.",
                ]);
            }
            $attributes = array_filter($attributes, fn (mixed $value): bool => $value !== null);
            if (! isset($attributes['email']) || $attributes['email'] === $this->placeholderEmail((string) $row->faculty_id_number)) {
                $attributes['email'] = $faculty->email;
            }
            $faculty->fill($attributes)->save();
        } else {
            throw ValidationException::withMessages(['import' => 'This import row has an unsupported action. Restage the file before confirming.']);
        }
        foreach (Arr::get($payload, 'custom_fields', []) as $key => $value) {
            $definition = $definitions->get($key);
            if (! $definition instanceof FacultyCustomFieldDefinition) {
                continue;
            }
            FacultyCustomFieldValue::query()->updateOrCreate(
                ['faculty_id' => $faculty->id, 'faculty_custom_field_definition_id' => $definition->id],
                ['school_id' => $schoolId, 'value' => $value],
            );
        }
        $row->update(['faculty_id' => $faculty->id, 'status' => 'applied', 'result' => ['action' => $row->action]]);
    }

    /**
     * @param  Collection<int, FacultyBulkImportRow>  $rows
     */
    private function authorizeRows(User $actor, Collection $rows): void
    {
        $gate = Gate::forUser($actor);
        if ($rows->contains(fn (FacultyBulkImportRow $row): bool => $row->action === 'create') && ! $gate->allows('create', Faculty::class)) {
            abort(403);
        }
        if ($rows->contains(fn (FacultyBulkImportRow $row): bool => $row->action === 'update') && ! $gate->allows('update', Faculty::class)) {
            abort(403);
        }
    }

    /**
     * @param  Collection<int, FacultyBulkImportRow>  $rows
     * @param  Collection<int, FacultyCustomFieldDefinition>  $definitions
     */
    private function validateRowsAgainstDefinitions(Collection $rows, Collection $definitions): void
    {
        $messages = [];
        foreach ($rows as $row) {
            $payload = $row->payload ?? [];
            $customFields = Arr::get($payload, 'custom_fields', []);
            $errors = [];
            $this->validateCustomFields(is_array($customFields) ? $customFields : [], $definitions, $errors);

            $missingDefinitions = collect($customFields)->keys()->diff($definitions->keys());
            foreach ($missingDefinitions as $key) {
                $errors[] = "The {$key} field definition no longer exists.";
            }
            if ($errors !== []) {
                $messages[] = "Row {$row->row_number}: ".implode(' ', $errors);
            }
        }
        if ($messages !== []) {
            throw ValidationException::withMessages([
                'import' => 'Faculty field definitions changed after staging. '.implode(' ', $messages).' Restage the file before confirming.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $customFields
     * @param  iterable<FacultyCustomFieldDefinition>  $definitions
     * @param  list<string>  $errors
     */
    private function validateCustomFields(array $customFields, iterable $definitions, array &$errors): void
    {
        foreach ($definitions as $definition) {
            $value = $this->string($customFields[$definition->key] ?? null);
            if ($definition->is_required && $value === null) {
                $errors[] = "{$definition->label} is required.";
            }
            $this->validateCustomValue($value, $definition, $errors);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{by_faculty_id_number: array<string, list<Faculty>>, by_email: array<string, list<Faculty>>}
     */
    private function facultyLookups(array $rows, int $schoolId): array
    {
        $facultyIds = [];
        $emails = [];
        foreach ($rows as $row) {
            $facultyId = $this->string($row['faculty_id_number'] ?? null);
            if ($facultyId === null) {
                continue;
            }
            $facultyIds[$this->normalizedFacultyId($facultyId)] = true;
            $email = $this->string($row['email'] ?? null) ?? $this->placeholderEmail($facultyId);
            $emails[$this->normalizedEmail($email)] = true;
        }

        $byFacultyIdNumber = [];
        foreach (array_chunk(array_keys($facultyIds), 500) as $ids) {
            Faculty::query()
                ->where('school_id', $schoolId)
                ->whereIn(DB::raw('lower(faculty_id_number)'), $ids)
                ->get(['id', 'faculty_id_number', 'email'])
                ->each(function (Faculty $faculty) use (&$byFacultyIdNumber): void {
                    $byFacultyIdNumber[$this->normalizedFacultyId($faculty->faculty_id_number)][] = $faculty;
                });
        }

        $byEmail = [];
        foreach (array_chunk(array_keys($emails), 500) as $emailBatch) {
            Faculty::query()
                ->where('school_id', $schoolId)
                ->whereIn(DB::raw('lower(email)'), $emailBatch)
                ->get(['id', 'faculty_id_number', 'email'])
                ->each(function (Faculty $faculty) use (&$byEmail): void {
                    $byEmail[$this->normalizedEmail($faculty->email)][] = $faculty;
                });
        }

        return ['by_faculty_id_number' => $byFacultyIdNumber, 'by_email' => $byEmail];
    }

    /** @param list<FacultyCustomFieldDefinition> $definitions
     * @return list<array<string, mixed>>
     */
    private function readRows(UploadedFile $file, array $definitions): array
    {
        $sourceType = $this->sourceType($file);
        $rawRows = $sourceType === 'mdb' ? $this->readMdb($file) : $this->readWorkbook($file);
        if ($rawRows === []) {
            throw ValidationException::withMessages(['file' => 'The upload does not contain any faculty rows.']);
        }
        if (count($rawRows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'An import can contain at most '.self::MAX_ROWS.' rows.']);
        }

        return array_map(fn (array $row): array => $this->canonicalizeRow($row, $definitions), $rawRows);
    }

    /** @return list<array<string, mixed>> */
    private function readWorkbook(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new FacultyBulkImportRowsImport, $file);
        foreach ($sheets as $sheet) {
            if (! is_array($sheet) || ! isset($sheet[0]) || ! is_array($sheet[0])) {
                continue;
            }
            $headers = array_map(fn (mixed $header): string => $this->normalizedHeader((string) $header), $sheet[0]);
            $hasFacultyId = in_array('faculty_id_number', $headers, true) || in_array('id_no', $headers, true);
            $hasName = in_array('first_name', $headers, true) || in_array('full_name', $headers, true);
            if (! $hasFacultyId || ! $hasName) {
                continue;
            }

            return collect(array_slice($sheet, 1))
                ->filter(fn (array $row): bool => collect($row)->contains(fn (mixed $value): bool => $this->string($value) !== null))
                ->map(fn (array $row): array => array_combine($headers, array_pad(array_values($row), count($headers), null)))
                ->values()
                ->all();
        }

        throw ValidationException::withMessages(['file' => 'The workbook needs a Faculty ID Number or ID_NO column and either First Name or Full Name.']);
    }

    /** @return list<array<string, mixed>> */
    private function readMdb(UploadedFile $file): array
    {
        // mdb-export includes the table header by default. Retain it so source
        // header aliases can be mapped to the school's active field definitions.
        $process = new Process(['mdb-export', $file->getRealPath(), 'employee']);
        $process->setTimeout(30);
        $process->run();
        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages(['file' => 'The Access file could not be read. Confirm it contains an employee table and try again.']);
        }
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'The Access file could not be processed.']);
        }
        fwrite($stream, $process->getOutput());
        rewind($stream);
        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            throw ValidationException::withMessages(['file' => 'The employee table has no readable headers.']);
        }
        $keys = array_map(fn (string $header): string => $this->normalizedHeader($header), $headers);
        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            if (count($row) === 1 && $this->string($row[0]) === null) {
                continue;
            }
            $rows[] = array_combine($keys, array_pad($row, count($keys), null));
        }
        fclose($stream);

        return $rows;
    }

    /** @param list<FacultyCustomFieldDefinition> $definitions
     * @return array<string, mixed>
     */
    private function canonicalizeRow(array $row, array $definitions): array
    {
        $normalized = [];
        foreach ($row as $header => $value) {
            $normalized[$this->normalizedHeader((string) $header)] = $value;
        }
        $result = [];
        foreach (self::STANDARD_FIELDS as $field) {
            $result[$field] = $normalized[$field] ?? null;
        }
        $result['faculty_id_number'] ??= $normalized['id_no'] ?? null;
        $result['address_line1'] ??= $normalized['address'] ?? null;
        $result['date_employed'] ??= $normalized['date_employed'] ?? null;
        if (isset($normalized['full_name']) && ($result['first_name'] === null || $result['last_name'] === null)) {
            $parts = $this->parseFullName((string) $normalized['full_name']);
            $result = [...$result, ...$parts];
            if ($parts['first_name'] === null || $parts['last_name'] === null) {
                $result['_warnings'][] = 'Full Name could not be confidently separated; complete the name before confirming.';
            }
        }
        $result['custom_fields'] = [];
        foreach ($definitions as $definition) {
            $aliases = array_merge(['custom_'.$definition->key, $definition->key], $definition->source_header_aliases ?? []);
            foreach ($aliases as $alias) {
                $key = $this->normalizedHeader($alias);
                if (array_key_exists($key, $normalized)) {
                    $result['custom_fields'][$definition->key] = $normalized[$key];
                    break;
                }
            }
        }

        return $result;
    }

    /** @return array{first_name: string|null, middle_name: string|null, last_name: string|null} */
    private function parseFullName(string $fullName): array
    {
        $fullName = $this->string($fullName);
        if ($fullName === null || ! str_contains($fullName, ',')) {
            return ['first_name' => null, 'middle_name' => null, 'last_name' => null];
        }
        [$lastName, $givenNames] = array_map(fn (string $part): string => mb_trim($part), explode(',', $fullName, 2));
        $parts = preg_split('/\s+/', $givenNames) ?: [];

        return [
            'first_name' => $parts[0] ?? null,
            'middle_name' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null,
            'last_name' => $lastName !== '' ? $lastName : null,
        ];
    }

    /** @param list<string> $errors */
    private function validateCustomValue(?string $value, FacultyCustomFieldDefinition $definition, array &$errors): void
    {
        if ($value === null) {
            return;
        }
        if ($definition->field_type === 'number' && ! is_numeric($value)) {
            $errors[] = "{$definition->label} must be a number.";
        } elseif ($definition->field_type === 'date' && $this->date($value, $definition->label, $errors) === null) {
            return;
        } elseif ($definition->field_type === 'select' && ! in_array($value, $definition->options ?? [], true)) {
            $errors[] = "{$definition->label} must use one of the configured options.";
        }
    }

    /** @param list<string> $errors */
    private function integer(mixed $value, string $label, array &$errors, int $min, int $max): ?int
    {
        if ($this->string($value) === null) {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $min || (int) $value > $max) {
            $errors[] = "{$label} must be between {$min} and {$max}.";

            return null;
        }

        return (int) $value;
    }

    /** @param list<string> $errors */
    private function date(mixed $value, string $label, array &$errors): ?string
    {
        $value = $this->string($value);
        if ($value === null) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            $errors[] = "{$label} must be a valid date.";

            return null;
        }
    }

    private function placeholderEmail(string $facultyId): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $local = Str::of($facultyId)->lower()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.')->toString();

        return ($local !== '' ? $local : 'faculty')."@{$host}";
    }

    private function normalizedFacultyId(string $facultyId): string
    {
        return mb_strtolower($facultyId);
    }

    private function normalizedEmail(string $email): string
    {
        return mb_strtolower($email);
    }

    private function normalizedHeader(string $header): string
    {
        return Str::of($header)->lower()->replaceMatches('/^custom\s*:\s*/', 'custom_')->snake()->trim('_')->toString();
    }

    private function sourceType(UploadedFile $file): string
    {
        return mb_strtolower($file->getClientOriginalExtension()) === 'mdb' ? 'mdb' : 'excel';
    }

    private function string(mixed $value): ?string
    {
        $value = mb_trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function schoolId(): int
    {
        $schoolId = $this->tenantContext->getCurrentSchoolId();
        if ($schoolId === null) {
            throw ValidationException::withMessages(['school' => 'Choose an active school before importing faculty.']);
        }

        return $schoolId;
    }

    private function refreshCounts(FacultyBulkImport $import): void
    {
        $counts = $import->rows()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $import->update([
            'ready_count' => (int) $counts->get('ready', 0),
            'invalid_count' => (int) $counts->get('invalid', 0),
            'applied_count' => (int) $counts->get('applied', 0),
            'skipped_count' => (int) $counts->get('skipped', 0),
        ]);
    }
}
