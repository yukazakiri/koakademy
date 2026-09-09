<?php

declare(strict_types=1);

namespace App\Services;

use App\Imports\CodeAuthorityImportRowsImport;
use App\Models\CodeAuthority;
use App\Models\CodeAuthorityImport;
use App\Models\CodeAuthorityImportRow;
use App\Models\IndustryCourseCode;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Two-phase (stage → confirm) spreadsheet import for per-school authority
 * code lists. Mirrors FacultyBulkImportService: unknown populated columns
 * become field proposals the registrar may adopt into the authority schema
 * at confirm time, so each country's spreadsheet shape is supported
 * without code changes.
 */
final readonly class CodeAuthorityImportService
{
    public function __construct(private TenantContext $tenantContext) {}

    public function stage(User $actor, CodeAuthority $authority, UploadedFile $file): CodeAuthorityImport
    {
        $schoolId = $this->schoolId();

        abort_unless($authority->school_id === $schoolId, 404);
        abort_unless($authority->is_active, 422, 'This authority is inactive.');

        $rawRows = $this->readRows($file);
        $fieldProposals = $this->fieldProposals($rawRows, $authority);
        $rows = array_map(
            fn (array $row): array => $this->canonicalizeRow($row, $authority, $fieldProposals),
            $rawRows,
        );

        $existing = IndustryCourseCode::query()
            ->where('school_id', $schoolId)
            ->where('code_authority_id', $authority->id)
            ->pluck('id', DB::raw('lower(code)'))
            ->all();

        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            throw ValidationException::withMessages(['file' => 'The upload checksum could not be calculated.']);
        }

        return DB::transaction(function () use ($actor, $file, $schoolId, $authority, $fieldProposals, $rows, $existing, $checksum): CodeAuthorityImport {
            $import = CodeAuthorityImport::query()->create([
                'public_id' => (string) Str::uuid(),
                'school_id' => $schoolId,
                'code_authority_id' => $authority->id,
                'uploaded_by_user_id' => $actor->id,
                'original_filename' => $file->getClientOriginalName(),
                'checksum' => $checksum,
                'field_proposals' => $fieldProposals,
                'status' => 'review',
            ]);

            $seen = [];
            foreach ($rows as $offset => $input) {
                $import->rows()->create($this->stageRow($input, $offset + 2, $schoolId, $existing, $seen));
            }
            $this->refreshCounts($import);

            return $import->refresh();
        });
    }

    /**
     * @param  list<int>  $rowIds
     * @param  list<string>  $adoptColumnKeys
     */
    public function confirm(CodeAuthorityImport $import, User $actor, array $rowIds, array $adoptColumnKeys = []): CodeAuthorityImport
    {
        $schoolId = $this->schoolId();

        if ($import->school_id !== $schoolId || $import->uploaded_by_user_id !== $actor->id) {
            abort(404);
        }

        return DB::transaction(function () use ($import, $actor, $rowIds, $adoptColumnKeys, $schoolId): CodeAuthorityImport {
            $locked = CodeAuthorityImport::query()
                ->whereKey($import->id)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'review') {
                throw ValidationException::withMessages([
                    'import' => 'This authority import has already been confirmed.',
                ]);
            }

            $adoptColumnKeys = array_values(array_unique($adoptColumnKeys));
            $proposals = collect($locked->field_proposals ?? [])->keyBy('key');
            $unknownKeys = array_values(array_diff($adoptColumnKeys, $proposals->keys()->all()));
            if ($unknownKeys !== []) {
                throw ValidationException::withMessages([
                    'adopt_column_keys' => 'One or more selected columns do not belong to this staged import.',
                ]);
            }

            $authority = $locked->authority()->lockForUpdate()->firstOrFail();
            abort_unless($authority->school_id === $schoolId, 404);
            $this->extendSchema($authority, $proposals->only($adoptColumnKeys)->values()->all());
            $this->mergeAdoptedAttributes($locked, $adoptColumnKeys);

            $rows = $locked->rows()->whereIn('id', $rowIds)->where('status', 'ready')->orderBy('row_number')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['row_ids' => 'Select at least one ready row to import.']);
            }

            $this->authorizeRows($actor, $rows);

            $locked->rows()
                ->where('status', 'ready')
                ->whereNotIn('id', $rows->pluck('id'))
                ->update([
                    'status' => 'skipped',
                    'result' => json_encode(['message' => 'Not selected for confirmation.']),
                    'updated_at' => now(),
                ]);

            foreach ($rows as $row) {
                $this->apply($row, $schoolId, $authority);
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
    public function serialize(CodeAuthorityImport $import): array
    {
        return [
            'id' => $import->public_id,
            'authority_id' => $import->code_authority_id,
            'status' => $import->status,
            'filename' => $import->original_filename,
            'summary' => [
                'ready_rows' => $import->ready_count,
                'invalid_rows' => $import->invalid_count,
                'applied_rows' => $import->applied_count,
                'skipped_rows' => $import->skipped_count,
            ],
            'field_proposals' => collect($import->field_proposals ?? [])
                ->map(fn (array $proposal): array => [
                    'key' => $proposal['key'],
                    'label' => $proposal['label'],
                    'source_header_aliases' => $proposal['source_header_aliases'],
                    'populated_rows' => $proposal['populated_rows'],
                ])
                ->values()
                ->all(),
            'rows' => $import->rows()->orderBy('row_number')->get()->map(fn (CodeAuthorityImportRow $row): array => [
                'id' => (string) $row->id,
                'source_row' => $row->row_number,
                'code' => $row->code,
                'title' => $row->title,
                'status' => $row->status,
                'action' => $row->action,
                'errors' => $row->errors ?? [],
                'warnings' => $row->warnings ?? [],
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, int>  $existing  lower(code) => id
     * @param  array<string, true>  $seen
     * @return array<string, mixed>
     */
    private function stageRow(array $input, int $rowNumber, int $schoolId, array $existing, array &$seen): array
    {
        $errors = [];
        $warnings = $input['_warnings'] ?? [];
        $code = $this->normalizeCode($input['code'] ?? null);
        $title = $this->string($input['title'] ?? null);

        if ($code === null) {
            $errors[] = 'Code is required.';
        } else {
            $lookup = mb_strtolower($code);
            if (isset($seen[$lookup])) {
                $errors[] = 'Code appears more than once in this upload.';
            } else {
                $seen[$lookup] = true;
            }
        }

        if ($title === null) {
            $errors[] = 'Title is required.';
        }

        $action = ($code !== null && isset($existing[mb_strtolower($code)])) ? 'update' : 'create';

        return [
            'school_id' => $schoolId,
            'row_number' => $rowNumber,
            'code' => $code,
            'title' => $title,
            'action' => $action,
            'payload' => [
                'code' => $code,
                'title' => $title,
                'attributes' => $input['attributes'] ?? [],
                'unmapped_attributes' => $input['unmapped_attributes'] ?? [],
            ],
            'errors' => $errors === [] ? null : $errors,
            'warnings' => $warnings === [] ? null : $warnings,
            'status' => $errors === [] ? 'ready' : 'invalid',
        ];
    }

    /** @param Collection<int, CodeAuthorityImportRow> $rows */
    private function authorizeRows(User $actor, Collection $rows): void
    {
        $hasCreate = $rows->contains(fn (CodeAuthorityImportRow $row): bool => $row->action === 'create');
        $hasUpdate = $rows->contains(fn (CodeAuthorityImportRow $row): bool => $row->action === 'update');

        if ($hasCreate && ! Gate::forUser($actor)->allows('create', IndustryCourseCode::class)) {
            abort(403, 'You do not have permission to create authority codes.');
        }

        if ($hasUpdate && ! Gate::forUser($actor)->allows('update', IndustryCourseCode::class)) {
            abort(403, 'You do not have permission to update authority codes.');
        }
    }

    private function apply(CodeAuthorityImportRow $row, int $schoolId, CodeAuthority $authority): void
    {
        $payload = $row->payload ?? [];
        $code = $this->normalizeCode($payload['code'] ?? $row->code);
        $title = $this->string($payload['title'] ?? $row->title);

        if ($code === null || $title === null) {
            throw ValidationException::withMessages([
                'import' => "Row {$row->row_number} is missing a code or title. Restage the file before confirming.",
            ]);
        }

        $record = IndustryCourseCode::query()
            ->where('school_id', $schoolId)
            ->where('code_authority_id', $authority->id)
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)])
            ->lockForUpdate()
            ->first();

        $attributes = is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [];

        if ($record instanceof IndustryCourseCode) {
            $record->forceFill([
                'code' => $code,
                'title' => $title,
                'attributes' => $attributes,
                'source' => 'import',
                'is_active' => true,
            ])->save();
        } else {
            $record = IndustryCourseCode::query()->create([
                'school_id' => $schoolId,
                'code_authority_id' => $authority->id,
                'code' => $code,
                'title' => $title,
                'attributes' => $attributes,
                'source' => 'import',
                'is_active' => true,
            ]);
        }

        $row->forceFill([
            'code' => $code,
            'title' => $title,
            'action' => 'applied',
            'status' => 'applied',
            'result' => ['industry_course_code_id' => $record->id],
        ])->save();
    }

    /**
     * @param  list<array{key: string, label: string, source_header_aliases: list<string>, populated_rows: int}>  $adopted
     */
    private function extendSchema(CodeAuthority $authority, array $adopted): void
    {
        if ($adopted === []) {
            return;
        }

        $definitions = $authority->columnDefinitions();
        $keys = array_fill_keys(array_column($definitions, 'key'), true);

        foreach ($adopted as $proposal) {
            if (isset($keys[$proposal['key']])) {
                continue;
            }
            $definitions[] = ['key' => $proposal['key'], 'label' => $proposal['label'], 'required' => false];
            $keys[$proposal['key']] = true;
        }

        $authority->forceFill(['schema' => $definitions])->save();
    }

    /** @param list<string> $adoptColumnKeys */
    private function mergeAdoptedAttributes(CodeAuthorityImport $import, array $adoptColumnKeys): void
    {
        if ($adoptColumnKeys === []) {
            return;
        }

        foreach ($import->rows()->where('status', 'ready')->orderBy('row_number')->get() as $row) {
            $payload = $row->payload ?? [];
            $attributes = is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [];
            $unmapped = is_array($payload['unmapped_attributes'] ?? null) ? $payload['unmapped_attributes'] : [];

            foreach ($adoptColumnKeys as $key) {
                if (array_key_exists($key, $unmapped)) {
                    $attributes[$key] = $unmapped[$key];
                    unset($unmapped[$key]);
                }
            }

            $payload['attributes'] = $attributes;
            $payload['unmapped_attributes'] = $unmapped;
            $row->payload = $payload;
            $row->save();
        }
    }

    /** @return list<array<string, mixed>> */
    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new CodeAuthorityImportRowsImport, $file);

        foreach ($sheets as $sheet) {
            if (! is_array($sheet) || ! isset($sheet[0]) || ! is_array($sheet[0])) {
                continue;
            }

            $headers = array_map(fn (mixed $header): string => $this->normalizedHeader((string) $header), $sheet[0]);
            $lookup = array_fill_keys($headers, true);
            $hasCode = isset($lookup['code']) || isset($lookup['psced_code']) || isset($lookup['6-digit_psced_code']) || isset($lookup['6_digit_psced_code']) || isset($lookup['program_code']) || isset($lookup['course_code']);
            $hasTitle = isset($lookup['title']) || isset($lookup['name']) || isset($lookup['program_title']) || isset($lookup['psced_name']) || isset($lookup['course_title']);

            if (! $hasCode || ! $hasTitle) {
                continue;
            }

            $rows = collect(array_slice($sheet, 1))
                ->filter(fn (array $row): bool => collect($row)->contains(fn (mixed $value): bool => $this->string($value) !== null))
                ->map(fn (array $row): array => array_combine($headers, array_pad(array_values($row), count($headers), null)))
                ->values()
                ->all();

            if ($rows === []) {
                continue;
            }

            if (count($rows) > $this->maxRows()) {
                throw ValidationException::withMessages(['file' => 'An import can contain at most '.$this->maxRows().' rows.']);
            }

            return $rows;
        }

        throw ValidationException::withMessages(['file' => 'The workbook needs a code column (e.g. Code) and a title column (e.g. Title or Name).']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key: string, label: string, source_header_aliases: list<string>, populated_rows: int}>
     */
    private function fieldProposals(array $rows, CodeAuthority $authority): array
    {
        $known = ['code', 'title', 'psced_code', '6-digit_psced_code', '6_digit_psced_code', 'program_code', 'course_code', 'name', 'program_title', 'psced_name', 'course_title'];
        foreach ($authority->columnDefinitions() as $definition) {
            $known[] = $this->normalizedHeader($definition['key']);
        }
        $known = array_fill_keys($known, true);

        $proposals = [];
        foreach ($rows as $row) {
            foreach ($row as $header => $value) {
                $normalized = $this->normalizedHeader((string) $header);
                if ($normalized === '' || isset($known[$normalized]) || $this->string($value) === null) {
                    continue;
                }
                $key = $this->proposalKey($normalized);
                if (! isset($proposals[$key])) {
                    $proposals[$key] = [
                        'key' => $key,
                        'label' => Str::of($normalized)->replace('_', ' ')->title()->toString(),
                        'source_header_aliases' => [$normalized],
                        'populated_rows' => 0,
                    ];
                }
                if (! in_array($normalized, $proposals[$key]['source_header_aliases'], true)) {
                    $proposals[$key]['source_header_aliases'][] = $normalized;
                }
                $proposals[$key]['populated_rows']++;
            }
        }

        return array_values($proposals);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{key: string, label: string, source_header_aliases: list<string>, populated_rows: int}>  $fieldProposals
     * @return array<string, mixed>
     */
    private function canonicalizeRow(array $row, CodeAuthority $authority, array $fieldProposals = []): array
    {
        $normalized = [];
        foreach ($row as $header => $value) {
            $normalized[$this->normalizedHeader((string) $header)] = $value;
        }

        $code = $normalized['code']
            ?? $normalized['psced_code']
            ?? $normalized['6-digit_psced_code']
            ?? $normalized['6_digit_psced_code']
            ?? $normalized['program_code']
            ?? $normalized['course_code']
            ?? null;
        $title = $normalized['title']
            ?? $normalized['name']
            ?? $normalized['program_title']
            ?? $normalized['psced_name']
            ?? $normalized['course_title']
            ?? null;

        $schemaKeys = array_map(fn (array $definition): string => $this->normalizedHeader($definition['key']), $authority->columnDefinitions());
        $attributes = [];
        foreach ($schemaKeys as $key) {
            if (in_array($key, ['code', 'title'], true)) {
                continue;
            }
            if (array_key_exists($key, $normalized) && $this->string($normalized[$key]) !== null) {
                $attributes[$key] = $this->string($normalized[$key]);
            }
        }

        $unmapped = [];
        foreach ($fieldProposals as $proposal) {
            foreach ($proposal['source_header_aliases'] as $sourceHeader) {
                if (array_key_exists($sourceHeader, $normalized) && $this->string($normalized[$sourceHeader]) !== null) {
                    $unmapped[$proposal['key']] = $this->string($normalized[$sourceHeader]);
                    break;
                }
            }
        }

        return [
            'code' => $this->string($code),
            'title' => $this->string($title),
            'attributes' => $attributes,
            'unmapped_attributes' => $unmapped,
        ];
    }

    /**
     * Normalize spreadsheet code artifacts: Excel stores 6-digit PSCED
     * codes as floats (140101.0); trailing ".0" is not part of the code.
     */
    private function normalizeCode(mixed $value): ?string
    {
        $string = $this->string($value);

        if ($string === null) {
            return null;
        }

        if (preg_match('/^\d+\.0+$/', $string) === 1) {
            $string = (string) preg_replace('/\.0+$/', '', $string);
        }

        return $string;
    }

    private function normalizedHeader(string $header): string
    {
        return Str::of($header)->lower()->replaceMatches('/^custom\s*:\s*/', 'custom_')->snake()->trim('_')->toString();
    }

    private function proposalKey(string $header): string
    {
        $key = Str::of(Str::after($header, 'custom_'))->snake()->toString();
        if ($key === '' || ! preg_match('/^[a-z]/', $key)) {
            $key = 'field_'.$key;
        }
        $key = (string) preg_replace('/[^a-z0-9_]/', '_', $key);

        return mb_substr($key, 0, 100);
    }

    private function string(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = mb_trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function maxRows(): int
    {
        return max(1, (int) config('authority-codes.import.max_rows', 10000));
    }

    private function schoolId(): int
    {
        $schoolId = $this->tenantContext->getCurrentSchoolId();

        if ($schoolId === null) {
            throw ValidationException::withMessages(['school' => 'Choose an active school before importing authority codes.']);
        }

        return $schoolId;
    }

    private function refreshCounts(CodeAuthorityImport $import): void
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
