<?php

declare(strict_types=1);

namespace App\Services;

use App\Exports\Sheets\TuitionAdjustmentSpreadsheetTemplateSheet;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\TuitionAdjustmentSpreadsheetImport;
use App\Models\TuitionAdjustmentSpreadsheetImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

final readonly class TuitionAdjustmentSpreadsheetImportService
{
    private const MAX_ROWS = 250;

    public function __construct(
        private TuitionAdjustmentService $adjustments,
        private TuitionPaymentScheduleSettingsService $scheduleSettings,
    ) {}

    public function stage(User $actor, UploadedFile $file, string $schoolYear, int $semester): TuitionAdjustmentSpreadsheetImport
    {
        $rows = $this->readRows($file);
        $path = $file->storeAs('tuition-adjustment-imports/'.now()->format('Y/m'), Str::uuid().'.xlsx', 'private');

        return DB::transaction(function () use ($actor, $file, $schoolYear, $semester, $rows, $path): TuitionAdjustmentSpreadsheetImport {
            $import = TuitionAdjustmentSpreadsheetImport::query()->create([
                'public_id' => (string) Str::uuid(),
                'uploaded_by_user_id' => $actor->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'school_year' => $schoolYear,
                'semester' => $semester,
                'status' => 'review',
            ]);

            $seenStudentNumbers = [];
            foreach ($rows as $offset => $row) {
                $staged = $this->stageRow($row, $offset + 2, $schoolYear, $semester, $seenStudentNumbers);
                $import->rows()->create($staged);
            }

            $this->refreshCounts($import);

            return $import;
        });
    }

    public function confirm(TuitionAdjustmentSpreadsheetImport $import, User $actor): TuitionAdjustmentSpreadsheetImport
    {
        return DB::transaction(function () use ($import, $actor): TuitionAdjustmentSpreadsheetImport {
            $locked = TuitionAdjustmentSpreadsheetImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'review') {
                return $locked->refresh();
            }

            $readyRows = $locked->rows()->where('status', 'ready')->orderBy('row_number')->get();
            if ($readyRows->isEmpty()) {
                throw ValidationException::withMessages(['import' => 'There are no valid spreadsheet rows to confirm.']);
            }

            $results = $this->adjustments->applyBatch(
                actor: $actor,
                batchKey: $locked->public_id,
                reason: 'Spreadsheet tuition adjustment import',
                rows: $readyRows->map(fn (TuitionAdjustmentSpreadsheetImportRow $row): array => [
                    ...($row->proposal ?? []),
                    'client_row_id' => 'spreadsheet-row-'.$row->id,
                ])->all(),
                source: 'spreadsheet',
            );
            $byClientRow = collect($results['rows'])->keyBy('client_row_id');

            foreach ($readyRows as $row) {
                $result = $byClientRow->get('spreadsheet-row-'.$row->id, []);
                $recorded = in_array($result['status'] ?? null, ['recorded', 'duplicate'], true);
                $row->update([
                    'status' => $recorded ? 'applied' : 'rejected',
                    'tuition_adjustment_id' => $result['adjustment_id'] ?? null,
                    'result' => $result,
                    'errors' => $recorded ? null : array_values(array_filter([(string) ($result['message'] ?? 'This row could not be applied.')])),
                ]);
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
    private function stageRow(array $input, int $rowNumber, string $schoolYear, int $semester, array &$seenStudentNumbers): array
    {
        $errors = [];
        $studentNumber = mb_trim((string) ($input['student_number'] ?? ''));
        $reason = mb_trim((string) ($input['reason'] ?? ''));
        $totalFees = $this->decimal($input['new_total_fees'] ?? null, 'New Total Fees', true, $errors);
        $openingPaid = $this->decimal($input['opening_paid'] ?? null, 'Opening Paid', false, $errors);
        $lecture = $this->decimal($input['lecture'] ?? null, 'Lecture', false, $errors);
        $laboratory = $this->decimal($input['laboratory'] ?? null, 'Laboratory', false, $errors);
        $miscellaneous = $this->decimal($input['miscellaneous'] ?? null, 'Miscellaneous', false, $errors);
        $discount = $this->decimal($input['discount'] ?? null, 'Discount %', false, $errors, 100);
        $downpayment = $this->decimal($input['required_downpayment'] ?? null, 'Required Downpayment', false, $errors);
        $installments = collect(['prelim', 'midterm', 'finals'])->mapWithKeys(fn (string $term): array => [$term => $this->decimal($input[$term] ?? null, ucfirst($term), false, $errors)])->all();

        if ($studentNumber === '') {
            $errors[] = 'Student Number is required.';
        } elseif (isset($seenStudentNumbers[mb_strtolower($studentNumber)])) {
            $errors[] = 'Student Number appears more than once in this file.';
        } else {
            $seenStudentNumbers[mb_strtolower($studentNumber)] = true;
        }
        if ($reason === '') {
            $errors[] = 'Reason is required.';
        }
        if (collect($installments)->contains(fn (?float $amount): bool => $amount !== null) && collect($installments)->contains(fn (?float $amount): bool => $amount === null)) {
            $errors[] = 'Enter all three installment amounts or leave all three blank.';
        }

        $base = [
            'row_number' => $rowNumber,
            'student_number' => $studentNumber ?: null,
            'input' => $input,
            'errors' => $errors,
            'status' => 'invalid',
        ];
        if ($errors !== []) {
            return $base;
        }

        $students = Student::query()->where('student_id', $studentNumber)->limit(2)->get();
        if ($students->count() !== 1) {
            return [...$base, 'errors' => [$students->isEmpty() ? 'Student Number could not be matched.' : 'Student Number matches multiple students.'], 'status' => 'invalid'];
        }
        $student = $students->sole();
        $enrollments = StudentEnrollment::query()
            ->with(['student.Course', 'course', 'studentTuition.installments', 'additionalFees'])
            ->where('student_id', $student->id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->whereHas('studentTuition')
            ->limit(2)
            ->get();
        if ($enrollments->count() !== 1) {
            return [...$base, 'student_id' => $student->id, 'errors' => [$enrollments->isEmpty() ? 'No tuition enrollment exists for the selected period.' : 'Multiple tuition enrollments exist for the selected period.'], 'status' => 'invalid'];
        }

        $enrollment = $enrollments->sole();
        $canonical = $this->adjustments->serialize($enrollment);
        $paid = $openingPaid ?? (float) $canonical['paid'];
        $balance = round($totalFees - $paid, 2);
        $overrides = collect($installments)->every(fn (?float $amount): bool => $amount !== null) ? $installments : null;
        $schedule = $this->scheduleSettings->installments(max(0, $balance), (string) $canonical['student_type'], $overrides);
        if (abs(collect($schedule)->sum('amount') - max(0, $balance)) > 0.009) {
            return [...$base, 'student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'student_tuition_id' => $canonical['tuition_id'], 'canonical_snapshot' => $canonical, 'errors' => ['Prelim, Midterm, and Finals must equal the remaining balance.'], 'status' => 'invalid'];
        }

        return [
            ...$base,
            'student_id' => $student->id,
            'student_enrollment_id' => $enrollment->id,
            'student_tuition_id' => $canonical['tuition_id'],
            'canonical_snapshot' => $canonical,
            'proposal' => [
                'reason' => $reason,
                'enrollment_id' => $enrollment->id,
                'tuition_id' => $canonical['tuition_id'],
                'state_hash' => $canonical['state_hash'],
                'total_fees' => $totalFees,
                'opening_paid' => $paid,
                'balance' => $balance,
                'lecture' => $lecture ?? (float) $canonical['lecture'],
                'laboratory' => $laboratory ?? (float) $canonical['laboratory'],
                'miscellaneous' => $miscellaneous ?? (float) $canonical['miscellaneous'],
                'discount' => (int) ($discount ?? $canonical['discount']),
                'required_downpayment' => $downpayment ?? (float) $canonical['required_downpayment'],
                'installments' => $overrides,
                'review_installments' => $schedule,
            ],
            'errors' => null,
            'status' => 'ready',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new stdClass, $file);
        $sheet = collect($sheets)->first(fn (array $rows): bool => $this->headingsMatch($rows[0] ?? []));
        if (! is_array($sheet)) {
            throw ValidationException::withMessages(['file' => 'The Tuition Adjustments sheet headers do not match the downloaded template.']);
        }
        $rows = collect(array_slice($sheet, 1))
            ->filter(fn (array $row): bool => collect($row)->contains(fn (mixed $value): bool => mb_trim((string) $value) !== ''))
            ->values();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'The workbook does not contain any adjustment rows.']);
        }
        if ($rows->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'A workbook can contain at most '.self::MAX_ROWS.' adjustment rows.']);
        }

        return $rows->map(fn (array $row): array => array_combine(
            array_map(fn (string $heading): string => Str::of($heading)->snake()->toString(), TuitionAdjustmentSpreadsheetTemplateSheet::HEADINGS),
            array_pad(array_values($row), count(TuitionAdjustmentSpreadsheetTemplateSheet::HEADINGS), null),
        ))->all();
    }

    private function headingsMatch(array $headings): bool
    {
        return array_values(array_map(fn (mixed $heading): string => mb_trim((string) $heading), $headings)) === TuitionAdjustmentSpreadsheetTemplateSheet::HEADINGS;
    }

    /** @param list<string> $errors */
    private function decimal(mixed $value, string $label, bool $required, array &$errors, ?float $maximum = null): ?float
    {
        if ($value === null || mb_trim((string) $value) === '') {
            if ($required) {
                $errors[] = "{$label} is required.";
            }

            return null;
        }
        if (! is_numeric($value) || (float) $value < 0 || ($maximum !== null && (float) $value > $maximum)) {
            $errors[] = $maximum === null ? "{$label} must be a non-negative number." : "{$label} must be between 0 and {$maximum}.";

            return null;
        }

        return round((float) $value, 2);
    }

    private function refreshCounts(TuitionAdjustmentSpreadsheetImport $import): void
    {
        $counts = $import->rows()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
        $import->update([
            'ready_count' => (int) $counts->get('ready', 0),
            'invalid_count' => (int) $counts->get('invalid', 0),
            'applied_count' => (int) $counts->get('applied', 0),
            'rejected_count' => (int) $counts->get('rejected', 0),
        ]);
    }
}
