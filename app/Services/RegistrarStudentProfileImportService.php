<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RegistrarStudentProfileImport;
use App\Models\RegistrarStudentProfileImportRow;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\RegistrarStudentProfileWorkbook;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

final readonly class RegistrarStudentProfileImportService
{
    private const int MAX_ROWS = 10000;

    public function __construct(
        private RegistrarStudentProfileWorkbook $workbook,
        private TenantContext $tenantContext,
    ) {}

    public function stage(User $actor, UploadedFile $file): RegistrarStudentProfileImport
    {
        $schoolId = $this->tenantContext->getCurrentSchoolId();
        if ($schoolId === null || ! $this->tenantContext->canAccessOrganization($schoolId)) {
            abort(403, 'A school must be selected before importing student information.');
        }

        $parsed = $this->parse($file, $schoolId);
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            throw ValidationException::withMessages(['file' => 'The workbook checksum could not be calculated.']);
        }

        return DB::transaction(function () use ($actor, $file, $schoolId, $parsed, $checksum): RegistrarStudentProfileImport {
            $import = RegistrarStudentProfileImport::query()->create([
                'public_id' => (string) Str::uuid(),
                'school_id' => $schoolId,
                'uploaded_by_user_id' => $actor->id,
                'original_filename' => $this->safeOriginalFilename($file),
                'checksum' => $checksum,
                'schema_version' => RegistrarStudentProfileWorkbook::SCHEMA_VERSION,
                'status' => 'review',
            ]);

            foreach ($parsed as $row) {
                $import->rows()->create([
                    ...$row,
                    'school_id' => $schoolId,
                ]);
            }

            $this->refreshCounts($import);

            return $import->refresh()->load('rows');
        }, attempts: 3);
    }

    /**
     * @param  list<int>  $studentIds
     */
    public function confirm(
        RegistrarStudentProfileImport $import,
        User $actor,
        array $studentIds,
    ): RegistrarStudentProfileImport {
        $schoolId = $this->tenantContext->getCurrentSchoolId();
        if ($schoolId === null || $import->school_id !== $schoolId || $import->uploaded_by_user_id !== $actor->id) {
            abort(404);
        }

        return DB::transaction(function () use ($import, $actor, $studentIds, $schoolId): RegistrarStudentProfileImport {
            $locked = RegistrarStudentProfileImport::query()
                ->whereKey($import->id)
                ->where('school_id', $schoolId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'review') {
                throw ValidationException::withMessages([
                    'import' => 'This workbook import has already been confirmed.',
                ]);
            }

            $rows = $locked->rows()
                ->where('status', 'ready')
                ->whereIn('student_id', $studentIds)
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Select at least one valid student to update.',
                ]);
            }

            $selectedIds = $rows->pluck('id');
            $locked->rows()
                ->where('status', 'ready')
                ->whereNotIn('id', $selectedIds)
                ->update([
                    'status' => 'skipped',
                    'result' => ['message' => 'Not selected for confirmation.'],
                    'updated_at' => now(),
                ]);

            foreach ($rows as $row) {
                $this->applyRow($row, $actor, $schoolId, $locked->public_id);
            }

            $locked->forceFill([
                'confirmed_by_user_id' => $actor->id,
                'confirmed_at' => now(),
                'status' => 'completed',
            ])->save();

            $this->refreshCounts($locked);

            return $locked->refresh()->load('rows');
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    public function serialize(RegistrarStudentProfileImport $import): array
    {
        $import->loadMissing('rows');

        $changes = $import->rows
            ->flatMap(fn (RegistrarStudentProfileImportRow $row): array => $row->changes ?? []);

        return [
            'id' => $import->public_id,
            'status' => $import->status,
            'filename' => $import->original_filename,
            'summary' => [
                'ready_students' => $import->ready_count,
                'invalid_students' => $import->invalid_count,
                'applied_students' => $import->applied_count,
                'skipped_students' => $import->skipped_count,
                'changed_fields' => $changes->count(),
                'new_freshmen' => $import->rows
                    ->filter(fn (RegistrarStudentProfileImportRow $row): bool => $this->effectiveIntakeCategory($row) === 'new_freshman')
                    ->count(),
                'continuing_first_year' => $import->rows
                    ->filter(fn (RegistrarStudentProfileImportRow $row): bool => $this->effectiveIntakeCategory($row) === 'continuing_first_year')
                    ->count(),
                'unclassified_first_year' => $import->rows
                    ->where('year_level', 1)
                    ->filter(fn (RegistrarStudentProfileImportRow $row): bool => $this->effectiveIntakeCategory($row) === null)
                    ->count(),
            ],
            'students' => $import->rows
                ->sortBy('row_number')
                ->map(fn (RegistrarStudentProfileImportRow $row): array => [
                    'id' => $row->id,
                    'student_id' => $row->student_id,
                    'student_number' => $row->student_number,
                    'student_name' => $row->student_name,
                    'course_code' => $row->course_code,
                    'year_level' => $row->year_level,
                    'intake_category' => $this->effectiveIntakeCategory($row),
                    'excel_row' => $row->row_number,
                    'status' => $row->status,
                    'changes' => $row->changes ?? [],
                    'errors' => $row->errors ?? [],
                    'warnings' => $row->warnings ?? [],
                    'result' => $row->result,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(UploadedFile $file, int $schoolId): array
    {
        $spreadsheet = null;

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($file->getRealPath());

            $metadata = $this->readMetadata($spreadsheet, $schoolId);
            $baselines = $this->readBaselines($spreadsheet, $schoolId);
            $details = $spreadsheet->getSheetByName(RegistrarStudentProfileWorkbook::DETAILS_SHEET);
            if (! $details instanceof Worksheet) {
                throw ValidationException::withMessages([
                    'file' => 'The Enrollment Details sheet is missing.',
                ]);
            }

            $this->assertHeadings($details);
            if ($details->getHighestDataRow() - 3 > self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook contains more than '.self::MAX_ROWS.' enrollment rows.',
                ]);
            }

            return $this->stageRows($details, $baselines, $schoolId, $metadata);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file' => 'The workbook could not be read. Download a fresh registrar export and try again.',
            ]);
        } finally {
            $spreadsheet?->disconnectWorksheets();
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    private function readMetadata(Spreadsheet $spreadsheet, int $schoolId): array
    {
        $sheet = $spreadsheet->getSheetByName(RegistrarStudentProfileWorkbook::METADATA_SHEET);
        if (! $sheet instanceof Worksheet) {
            throw ValidationException::withMessages(['file' => 'This is not an import-enabled registrar workbook.']);
        }

        $pairs = [];
        for ($row = 1; $row <= 5; $row++) {
            $pairs[(string) $sheet->getCell("A{$row}")->getValue()] = $sheet->getCell("B{$row}")->getValue();
        }

        $metadata = [
            'schema_version' => (int) ($pairs['Schema Version'] ?? 0),
            'school_id' => (int) ($pairs['School ID'] ?? 0),
            'report_label' => (string) ($pairs['Report Label'] ?? ''),
            'generated_at' => (string) ($pairs['Generated At'] ?? ''),
        ];
        $signature = (string) ($pairs['Signature'] ?? '');

        if ($metadata['schema_version'] !== RegistrarStudentProfileWorkbook::SCHEMA_VERSION) {
            throw ValidationException::withMessages(['file' => 'This workbook version is no longer supported. Download a new export.']);
        }
        if ($metadata['school_id'] !== $schoolId) {
            throw ValidationException::withMessages(['file' => 'This workbook belongs to a different school.']);
        }
        if (! $this->workbook->verifyMetadata($metadata, $signature)) {
            throw ValidationException::withMessages(['file' => 'The workbook metadata signature is invalid.']);
        }

        return $metadata;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readBaselines(Spreadsheet $spreadsheet, int $schoolId): array
    {
        $sheet = $spreadsheet->getSheetByName(RegistrarStudentProfileWorkbook::BASELINE_SHEET);
        if (! $sheet instanceof Worksheet) {
            throw ValidationException::withMessages(['file' => 'The protected import baseline is missing.']);
        }

        $expected = [
            'Record Key',
            'Student Record ID',
            'Enrollment Record ID',
            'Student Updated At',
            'Enrollment Updated At',
            'Year Level',
            'Intake Category',
            'Profile Values',
            'Signature',
        ];
        $actual = [];
        foreach (range(1, count($expected)) as $column) {
            $actual[] = (string) $sheet->getCell([$column, 1])->getValue();
        }
        if ($actual !== $expected) {
            throw ValidationException::withMessages(['file' => 'The protected import baseline is invalid.']);
        }

        $baselines = [];
        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $recordKey = mb_trim((string) $sheet->getCell([1, $row])->getValue());
            if ($recordKey === '') {
                continue;
            }

            try {
                $profileValues = json_decode(
                    (string) $sheet->getCell([8, $row])->getValue(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                throw ValidationException::withMessages(['file' => "The protected baseline is invalid at row {$row}."]);
            }

            $baseline = [
                'schema_version' => RegistrarStudentProfileWorkbook::SCHEMA_VERSION,
                'record_key' => $recordKey,
                'school_id' => $schoolId,
                'student_record_id' => (int) $sheet->getCell([2, $row])->getValue(),
                'enrollment_record_id' => (int) $sheet->getCell([3, $row])->getValue(),
                'student_updated_at' => (string) $sheet->getCell([4, $row])->getValue(),
                'enrollment_updated_at' => (string) $sheet->getCell([5, $row])->getValue(),
                'profile_values' => is_array($profileValues) ? $profileValues : [],
                'intake_category' => $this->nullableString($sheet->getCell([7, $row])->getValue()),
                'year_level' => (int) $sheet->getCell([6, $row])->getValue(),
            ];
            $signature = (string) $sheet->getCell([9, $row])->getValue();

            if (! $this->workbook->verifyBaseline($baseline, $signature)) {
                throw ValidationException::withMessages(['file' => "The protected baseline signature is invalid at row {$row}."]);
            }

            $identity = [
                'student_record_id' => $baseline['student_record_id'],
                'enrollment_record_id' => $baseline['enrollment_record_id'],
                'student_updated_at' => $baseline['student_updated_at'],
                'enrollment_updated_at' => $baseline['enrollment_updated_at'],
            ];
            if (! hash_equals($recordKey, $this->workbook->recordKey($identity, $schoolId))) {
                throw ValidationException::withMessages(['file' => "The protected record identity is invalid at row {$row}."]);
            }

            $baselines[$recordKey] = $baseline;
        }

        if ($baselines === []) {
            throw ValidationException::withMessages(['file' => 'The workbook does not contain any importable enrollment rows.']);
        }

        return $baselines;
    }

    private function assertHeadings(Worksheet $sheet): void
    {
        $actual = [];
        foreach (range(1, count($this->workbook->headings())) as $column) {
            $actual[] = mb_trim((string) $sheet->getCell([$column, 3])->getValue());
        }

        if ($actual !== $this->workbook->headings()) {
            throw ValidationException::withMessages([
                'file' => 'The Enrollment Details headings were changed. Download a fresh registrar export.',
            ]);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $baselines
     * @param  array<string, scalar|null>  $metadata
     * @return list<array<string, mixed>>
     */
    private function stageRows(
        Worksheet $sheet,
        array $baselines,
        int $schoolId,
        array $metadata,
    ): array {
        $studentIds = collect($baselines)->pluck('student_record_id')->map(fn (mixed $id): int => (int) $id)->unique();
        $enrollmentIds = collect($baselines)->pluck('enrollment_record_id')->map(fn (mixed $id): int => (int) $id)->unique();

        $students = Student::query()
            ->with(['studentContactsInfo', 'studentParentInfo', 'studentEducationInfo', 'personalInfo'])
            ->where('school_id', $schoolId)
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');
        $enrollments = StudentEnrollment::query()
            ->with('course:id,code')
            ->where('school_id', $schoolId)
            ->whereIn('id', $enrollmentIds)
            ->get()
            ->keyBy('id');

        $groups = [];
        $fieldColumns = $this->workbook->fieldKeysByHeading();

        for ($rowNumber = 4; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $recordKey = mb_trim((string) $sheet->getCell([1, $rowNumber])->getValue());
            if ($recordKey === '') {
                if ($this->rowHasData($sheet, $rowNumber)) {
                    throw ValidationException::withMessages(['file' => "Enrollment Details row {$rowNumber} has no protected record key."]);
                }

                continue;
            }

            $baseline = $baselines[$recordKey] ?? null;
            if (! is_array($baseline)) {
                throw ValidationException::withMessages(['file' => "Enrollment Details row {$rowNumber} has an unknown record key."]);
            }

            $studentId = (int) $baseline['student_record_id'];
            $enrollmentId = (int) $baseline['enrollment_record_id'];
            $student = $students->get($studentId);
            $enrollment = $enrollments->get($enrollmentId);

            $group = $groups[$studentId] ?? [
                'row_number' => $rowNumber,
                'student_id' => $student instanceof Student ? $student->id : null,
                'student_enrollment_id' => $enrollment instanceof StudentEnrollment ? $enrollment->id : null,
                'student_number' => $student instanceof Student ? (string) $student->student_id : null,
                'student_name' => $student instanceof Student ? $student->full_name : 'Unavailable student',
                'course_code' => $enrollment instanceof StudentEnrollment ? $enrollment->course?->code : null,
                'year_level' => (int) $baseline['year_level'],
                'intake_category' => $enrollment instanceof StudentEnrollment ? $enrollment->intake_category : null,
                'changes_by_key' => [],
                'errors' => [],
                'warnings' => [],
            ];

            if (! $student instanceof Student || ! $enrollment instanceof StudentEnrollment || (int) $enrollment->student_id !== $studentId) {
                $group['errors'][] = "Excel row {$rowNumber}: the student or enrollment no longer exists.";
                $groups[$studentId] = $group;

                continue;
            }

            $currentProfile = $this->workbook->profileValues($student);
            foreach ($fieldColumns as $heading => $fieldKey) {
                $column = array_search($heading, $this->workbook->headings(), true);
                if ($column === false) {
                    continue;
                }

                $cell = $sheet->getCell([(int) $column + 1, $rowNumber]);
                if ($cell->isFormula()) {
                    $group['errors'][] = "Excel row {$rowNumber}: {$heading} cannot contain a formula.";

                    continue;
                }

                $raw = $this->normalizeSpreadsheetDate($fieldKey, $cell->getValue());
                if (mb_trim((string) $raw) === '') {
                    continue;
                }

                $oldValue = $baseline['profile_values'][$fieldKey] ?? null;
                if (mb_trim((string) $raw) === mb_trim($this->workbook->displayValue($fieldKey, $oldValue))) {
                    continue;
                }

                [$newValue, $error] = $this->workbook->normalizeInput($fieldKey, $raw);
                if ($error !== null) {
                    $group['errors'][] = "Excel row {$rowNumber}: {$error}";

                    continue;
                }

                if ($this->workbook->valuesEqual($newValue, $oldValue)) {
                    continue;
                }

                $currentValue = $currentProfile[$fieldKey] ?? null;
                if (! $this->workbook->valuesEqual($currentValue, $oldValue)) {
                    $group['errors'][] = "Excel row {$rowNumber}: {$heading} changed in the system after this workbook was exported.";

                    continue;
                }

                $field = $this->workbook->field($fieldKey);
                $this->mergeChange($group, [
                    'key' => $fieldKey,
                    'label' => $heading,
                    'group' => $field['group'] ?? 'Student Profile',
                    'target' => 'student',
                    'target_id' => $studentId,
                    'old' => $oldValue,
                    'new' => $newValue,
                    'excel_row' => $rowNumber,
                ]);
            }

            $intakeCell = $sheet->getCell([8, $rowNumber]);
            if ($intakeCell->isFormula()) {
                $group['errors'][] = "Excel row {$rowNumber}: First-year Intake Classification cannot contain a formula.";
            } else {
                $this->stageIntakeChange($group, $intakeCell->getValue(), $baseline, $enrollment, $rowNumber);
            }

            $groups[$studentId] = $group;
        }

        $rows = collect($groups)
            ->filter(fn (array $group): bool => $group['changes_by_key'] !== [] || $group['errors'] !== [])
            ->map(function (array $group) use ($schoolId, $metadata): array {
                $changes = array_values($group['changes_by_key']);
                $warnings = $group['warnings'];
                if ($group['year_level'] === 1 && blank($group['intake_category'])
                    && collect($changes)->where('target', 'enrollment')->isEmpty()) {
                    $warnings[] = 'This first-year enrollment remains unclassified.';
                }

                return [
                    'school_id' => $schoolId,
                    'row_number' => $group['row_number'],
                    'student_id' => $group['student_id'],
                    'student_enrollment_id' => $group['student_enrollment_id'],
                    'student_number' => $group['student_number'],
                    'student_name' => $group['student_name'],
                    'course_code' => $group['course_code'],
                    'year_level' => $group['year_level'],
                    'intake_category' => $group['intake_category'],
                    'changes' => $changes,
                    'errors' => array_values(array_unique($group['errors'])),
                    'warnings' => array_values(array_unique($warnings)),
                    'result' => ['workbook_generated_at' => $metadata['generated_at']],
                    'status' => $group['errors'] === [] && $changes !== [] ? 'ready' : 'invalid',
                ];
            })
            ->values()
            ->all();

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'No student profile or first-year classification changes were found.',
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $change
     */
    private function mergeChange(array &$group, array $change): void
    {
        $changeKey = $change['target'].':'.$change['target_id'].':'.$change['key'];
        $existing = $group['changes_by_key'][$changeKey] ?? null;

        if (is_array($existing) && ! $this->workbook->valuesEqual($existing['new'], $change['new'])) {
            $group['errors'][] = sprintf(
                'Excel rows %d and %d contain conflicting values for %s.',
                $existing['excel_row'],
                $change['excel_row'],
                $change['label'],
            );

            return;
        }

        $group['changes_by_key'][$changeKey] = $change;
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $baseline
     */
    private function stageIntakeChange(
        array &$group,
        mixed $raw,
        array $baseline,
        StudentEnrollment $enrollment,
        int $rowNumber,
    ): void {
        $text = mb_trim((string) $raw);
        $yearLevel = (int) $baseline['year_level'];

        if ($yearLevel !== 1) {
            if ($text !== '' && mb_strtolower($text) !== 'not applicable') {
                $group['errors'][] = "Excel row {$rowNumber}: first-year classification is only available for Year 1 enrollments.";
            }

            return;
        }

        [$newValue, $error] = $this->workbook->normalizeIntakeCategory($text);
        if ($error !== null) {
            $group['errors'][] = "Excel row {$rowNumber}: {$error}";

            return;
        }

        $oldValue = $baseline['intake_category'];
        if ($this->workbook->valuesEqual($newValue, $oldValue)) {
            return;
        }
        if ((int) $enrollment->academic_year !== 1 || ! $this->workbook->valuesEqual($enrollment->intake_category, $oldValue)) {
            $group['errors'][] = "Excel row {$rowNumber}: the first-year classification changed after this workbook was exported.";

            return;
        }

        $this->mergeChange($group, [
            'key' => 'intake_category',
            'label' => 'First-year Intake Classification',
            'group' => 'Enrollment',
            'target' => 'enrollment',
            'target_id' => $enrollment->id,
            'old' => $oldValue,
            'new' => $newValue,
            'excel_row' => $rowNumber,
        ]);
    }

    private function applyRow(
        RegistrarStudentProfileImportRow $row,
        User $actor,
        int $schoolId,
        string $batchId,
    ): void {
        $student = Student::query()
            ->where('school_id', $schoolId)
            ->whereKey($row->student_id)
            ->lockForUpdate()
            ->first();

        if (! $student instanceof Student) {
            $this->skipRow($row, 'The student no longer exists.');

            return;
        }

        $student->load(['studentContactsInfo', 'studentParentInfo', 'studentEducationInfo', 'personalInfo']);
        $currentProfile = $this->workbook->profileValues($student);
        $changes = $row->changes ?? [];
        $enrollmentIds = collect($changes)
            ->where('target', 'enrollment')
            ->pluck('target_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $enrollments = StudentEnrollment::query()
            ->where('school_id', $schoolId)
            ->where('student_id', (string) $student->id)
            ->whereIn('id', $enrollmentIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($changes as $change) {
            if (($change['target'] ?? null) === 'student') {
                $current = $currentProfile[$change['key']] ?? null;
            } else {
                $enrollment = $enrollments->get((int) $change['target_id']);
                if (! $enrollment instanceof StudentEnrollment) {
                    $this->skipRow($row, 'The enrollment no longer exists.');

                    return;
                }
                if ((int) $enrollment->academic_year !== 1) {
                    $this->skipRow($row, 'The enrollment is no longer classified as Year 1.');

                    return;
                }

                $current = $enrollment->intake_category;
            }

            if (! $this->workbook->valuesEqual($current, $change['old'] ?? null)) {
                $this->skipRow($row, ($change['label'] ?? 'A field').' changed after the preview was created.');

                return;
            }
        }

        $studentChanges = collect($changes)->where('target', 'student')->values()->all();
        if ($studentChanges !== []) {
            $this->applyStudentChanges($student, $studentChanges);
        }

        foreach (collect($changes)->where('target', 'enrollment') as $change) {
            /** @var StudentEnrollment $enrollment */
            $enrollment = $enrollments->get((int) $change['target_id']);
            $enrollment->update(['intake_category' => $change['new']]);
        }

        activity('registrar_student_profile_import')
            ->causedBy($actor)
            ->performedOn($student)
            ->event('bulk_updated')
            ->withProperties([
                'batch_id' => $batchId,
                'changes' => $changes,
            ])
            ->log('Student information was updated from a confirmed registrar workbook.');

        $intakeChange = collect($changes)->firstWhere('key', 'intake_category');
        $row->update([
            'status' => 'applied',
            'intake_category' => is_array($intakeChange) ? $intakeChange['new'] : $row->intake_category,
            'result' => [
                'message' => count($changes).' field change(s) applied.',
                'applied_at' => now()->toIso8601String(),
            ],
            'errors' => [],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    private function applyStudentChanges(Student $student, array $changes): void
    {
        $related = [
            'contact' => ['table' => 'student_contacts', 'foreign_key' => 'student_contact_id'],
            'parent' => ['table' => 'student_parents_info', 'foreign_key' => 'student_parent_info'],
            'education' => ['table' => 'student_education_info', 'foreign_key' => 'student_education_id'],
            'personal' => ['table' => 'students_personal_info', 'foreign_key' => 'student_personal_id'],
        ];
        $relatedUpdates = [];
        $contacts = is_array($student->contacts) ? $student->contacts : [];

        foreach ($changes as $change) {
            foreach ($this->workbook->writeTargets((string) $change['key']) as $target) {
                [$scope, $attribute] = explode('.', $target, 2);
                $value = $change['new'];

                if ($scope === 'student') {
                    $student->setAttribute($attribute, $value);

                    continue;
                }
                if ($scope === 'contacts') {
                    data_set($contacts, $attribute, $value);

                    continue;
                }
                if (isset($related[$scope])) {
                    $relatedUpdates[$scope][$attribute] = $value;
                }
            }

            if ($change['key'] === 'birth_date') {
                $student->age = Carbon::parse((string) $change['new'])->age;
            }
        }

        $student->contacts = $contacts;

        foreach ($relatedUpdates as $scope => $attributes) {
            $definition = $related[$scope];
            $columns = Schema::getColumnListing($definition['table']);
            $attributes = Arr::only($attributes, $columns);
            if ($scope === 'contact' && in_array('student_id', $columns, true)) {
                $attributes['student_id'] = $student->id;
            }
            if ($attributes === []) {
                continue;
            }

            $foreignKey = $definition['foreign_key'];
            $relatedId = $student->getAttribute($foreignKey);
            if ($relatedId !== null) {
                if (in_array('updated_at', $columns, true)) {
                    $attributes['updated_at'] = now();
                }
                DB::table($definition['table'])->where('id', $relatedId)->update($attributes);

                continue;
            }

            if (in_array('created_at', $columns, true)) {
                $attributes['created_at'] = now();
            }
            if (in_array('updated_at', $columns, true)) {
                $attributes['updated_at'] = now();
            }

            $student->setAttribute($foreignKey, DB::table($definition['table'])->insertGetId($attributes));
        }

        $student->save();
        $student->refresh();
    }

    private function skipRow(RegistrarStudentProfileImportRow $row, string $message): void
    {
        $row->update([
            'status' => 'skipped',
            'errors' => [$message],
            'result' => ['message' => $message],
        ]);
    }

    private function effectiveIntakeCategory(RegistrarStudentProfileImportRow $row): ?string
    {
        $change = collect($row->changes ?? [])->firstWhere('key', 'intake_category');
        if (is_array($change) && array_key_exists('new', $change)) {
            return is_string($change['new']) ? $change['new'] : null;
        }

        return $row->intake_category;
    }

    private function refreshCounts(RegistrarStudentProfileImport $import): void
    {
        $counts = $import->rows()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        $import->update([
            'ready_count' => (int) $counts->get('ready', 0),
            'invalid_count' => (int) $counts->get('invalid', 0),
            'applied_count' => (int) $counts->get('applied', 0),
            'skipped_count' => (int) $counts->get('skipped', 0),
        ]);
    }

    private function rowHasData(Worksheet $sheet, int $row): bool
    {
        $lastColumn = count($this->workbook->headings());

        for ($column = 2; $column <= $lastColumn; $column++) {
            if (mb_trim((string) $sheet->getCell([$column, $row])->getValue()) !== '') {
                return true;
            }
        }

        return false;
    }

    private function normalizeSpreadsheetDate(string $fieldKey, mixed $value): mixed
    {
        $field = $this->workbook->field($fieldKey);
        if (($field['type'] ?? null) !== 'date' || ! is_numeric($value)) {
            return $value;
        }

        try {
            return SpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        } catch (Throwable) {
            return $value;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = mb_trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $name = basename(str_replace(["\r", "\n", "\0"], '', $file->getClientOriginalName()));

        return Str::limit($name === '' ? 'registrar-import.xlsx' : $name, 255, '');
    }
}
