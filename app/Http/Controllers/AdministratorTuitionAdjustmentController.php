<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Exports\TuitionAdjustmentSpreadsheetTemplateExport;
use App\Http\Requests\Administrators\ConfirmTuitionAdjustmentSpreadsheetImportRequest;
use App\Http\Requests\Administrators\ResolveTuitionAdjustmentRowsRequest;
use App\Http\Requests\Administrators\StoreTuitionAdjustmentBatchRequest;
use App\Http\Requests\Administrators\StoreTuitionAdjustmentSpreadsheetImportRequest;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\TuitionAdjustment;
use App\Models\TuitionAdjustmentSpreadsheetImport;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\TuitionAdjustmentNotificationService;
use App\Services\TuitionAdjustmentService;
use App\Services\TuitionAdjustmentSpreadsheetImportService;
use App\Services\TuitionPaymentScheduleSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AdministratorTuitionAdjustmentController extends Controller
{
    public function __construct(
        private readonly TuitionAdjustmentService $adjustments,
        private readonly TuitionPaymentScheduleSettingsService $scheduleSettings,
        private readonly TuitionAdjustmentNotificationService $notifications,
    ) {}

    public function index(Request $request, GeneralSettingsService $settings): Response
    {
        abort_unless($request->user()?->can('view_tuition_fees'), 403);

        $schoolYear = $request->string('school_year')->toString() ?: $settings->getCurrentSchoolYearString();
        $semester = $request->integer('semester', $settings->getCurrentSemester());
        $query = StudentEnrollment::query()
            ->with(['student.Course', 'course', 'studentTuition.installments', 'additionalFees'])
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->whereHas('studentTuition')
            ->when($request->filled('enrollment'), fn ($builder) => $builder->whereKey($request->integer('enrollment')))
            ->when($request->filled('student'), fn ($builder) => $builder->where('student_id', $request->integer('student')))
            ->when($request->filled('course_id'), fn ($builder) => $builder->where('course_id', $request->integer('course_id')))
            ->orderByDesc('id')
            ->limit(250)
            ->get();

        return Inertia::render('administrators/finance/tuition-adjustments', [
            'user' => $request->user(),
            'rows' => $query->map(fn (StudentEnrollment $enrollment): array => $this->adjustments->serialize($enrollment))->values(),
            'filters' => ['school_year' => $schoolYear, 'semester' => $semester],
            'school_years' => $settings->getAvailableSchoolYears(),
            'semesters' => $settings->getAvailableSemesters(),
            'courses' => Course::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'title']),
            'student_types' => collect(StudentType::cases())->map(fn (StudentType $type): array => ['value' => $type->value, 'label' => $type->getLabel()])->values(),
            'schedule_settings' => $this->scheduleSettings->get(),
            'workspace_layout' => data_get($request->user()?->preferences, 'finance.tuition_adjustments.layout', 'inspector'),
            'can_manage' => $request->user()?->can('manage_tuition_fees') ?? false,
        ]);
    }

    public function resolve(ResolveTuitionAdjustmentRowsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $results = collect($validated['rows'])->map(function (array $row) use ($validated): array {
            $student = $this->resolveStudent((string) $row['identifier']);
            if (! $student instanceof Student) {
                return ['client_row_id' => $row['client_row_id'], 'status' => 'rejected', 'message' => 'Student could not be matched.'];
            }

            $enrollment = StudentEnrollment::query()
                ->with(['student.Course', 'course', 'studentTuition.installments', 'additionalFees'])
                ->where('student_id', $student->id)
                ->where('school_year', $validated['school_year'])
                ->where('semester', $validated['semester'])
                ->whereHas('studentTuition')
                ->latest('id')
                ->first();
            if (! $enrollment instanceof StudentEnrollment) {
                return ['client_row_id' => $row['client_row_id'], 'status' => 'rejected', 'message' => 'No tuition enrollment exists for the selected period.'];
            }

            $canonical = $this->adjustments->serialize($enrollment);
            foreach (['total_fees', 'opening_paid', 'balance'] as $field) {
                if (array_key_exists($field, $row) && $row[$field] !== null) {
                    $canonical[$field === 'opening_paid' ? 'paid' : $field] = (float) $row[$field];
                }
            }
            if (collect(['prelim', 'midterm', 'finals'])->every(fn (string $term): bool => isset($row[$term]))) {
                $canonical['installments'] = collect(['prelim', 'midterm', 'finals'])->map(fn (string $term): array => ['term' => $term, 'amount' => (float) $row[$term], 'source' => 'manual'])->all();
            }

            return ['client_row_id' => $row['client_row_id'], 'status' => 'resolved', 'canonical' => $canonical];
        })->values();

        return response()->json(['rows' => $results]);
    }

    public function storeBatch(StoreTuitionAdjustmentBatchRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validated();

        return response()->json($this->adjustments->applyBatch(
            actor: $user,
            batchKey: $validated['batch_key'],
            reason: $validated['reason'],
            rows: $validated['rows'],
            source: $validated['source'] ?? 'workspace',
        ));
    }

    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('view_tuition_fees'), 403);

        return Excel::download(new TuitionAdjustmentSpreadsheetTemplateExport, 'tuition-adjustment-template.xlsx');
    }

    public function storeSpreadsheetImport(StoreTuitionAdjustmentSpreadsheetImportRequest $request, TuitionAdjustmentSpreadsheetImportService $imports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validated();
        $import = $imports->stage($user, $validated['file'], $validated['school_year'], $validated['semester']);

        return redirect()->route('administrators.finance.tuition-adjustments.imports.show', $import)
            ->with('success', 'Spreadsheet uploaded. Review valid rows before confirming tuition changes.');
    }

    public function showSpreadsheetImport(Request $request, TuitionAdjustmentSpreadsheetImport $spreadsheetImport): Response
    {
        abort_unless($request->user()?->can('manage_tuition_fees'), 403);
        $spreadsheetImport->load([
            'uploader:id,name', 'confirmer:id,name',
            'rows' => fn ($query) => $query->with(['adjustment:id,source,reason'])->orderBy('row_number'),
        ]);

        return Inertia::render('administrators/finance/tuition-adjustment-spreadsheet-import', [
            'user' => $request->user(),
            'import' => [
                'id' => $spreadsheetImport->public_id,
                'filename' => $spreadsheetImport->original_filename,
                'school_year' => $spreadsheetImport->school_year,
                'semester' => $spreadsheetImport->semester,
                'status' => $spreadsheetImport->status,
                'counts' => [
                    'ready' => $spreadsheetImport->ready_count, 'invalid' => $spreadsheetImport->invalid_count,
                    'applied' => $spreadsheetImport->applied_count, 'rejected' => $spreadsheetImport->rejected_count,
                ],
                'uploaded_at' => $spreadsheetImport->created_at?->toIso8601String(),
                'confirmed_at' => $spreadsheetImport->confirmed_at?->toIso8601String(),
                'uploader' => $spreadsheetImport->uploader?->only(['id', 'name']),
                'confirmer' => $spreadsheetImport->confirmer?->only(['id', 'name']),
                'rows' => $spreadsheetImport->rows->map(fn ($row): array => [
                    'id' => $row->id, 'row_number' => $row->row_number, 'student_number' => $row->student_number,
                    'status' => $row->status, 'input' => $row->input, 'canonical' => $row->canonical_snapshot,
                    'proposal' => $row->proposal, 'errors' => $row->errors ?? [], 'result' => $row->result,
                ])->values(),
            ],
            'can_confirm' => $spreadsheetImport->status === 'review' && ($request->user()?->can('manage_tuition_fees') ?? false),
        ]);
    }

    public function confirmSpreadsheetImport(ConfirmTuitionAdjustmentSpreadsheetImportRequest $request, TuitionAdjustmentSpreadsheetImport $spreadsheetImport, TuitionAdjustmentSpreadsheetImportService $imports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $imports->confirm($spreadsheetImport, $user);

        return redirect()->route('administrators.finance.tuition-adjustments.imports.show', $spreadsheetImport)
            ->with('success', 'Valid spreadsheet rows were confirmed and applied.');
    }

    public function retryNotification(Request $request, TuitionAdjustment $adjustment): JsonResponse
    {
        abort_unless($request->user()?->can('manage_tuition_fees'), 403);

        return response()->json(['delivery_status' => $this->notifications->send($adjustment)]);
    }

    private function resolveStudent(string $identifier): ?Student
    {
        $normalized = mb_strtolower(mb_trim($identifier));
        $exact = Student::query()
            ->where('student_id', $identifier)
            ->orWhereRaw('lower(email) = ?', [$normalized])
            ->first();
        if ($exact instanceof Student) {
            return $exact;
        }

        $lastName = mb_strtolower(mb_trim(Str::before($identifier, ',')));

        return Student::query()
            ->whereRaw('lower(last_name) = ?', [$lastName])
            ->limit(10)
            ->get()
            ->first(fn (Student $student): bool => str_contains(mb_strtolower($student->full_name), str_replace(',', '', $normalized)) || str_contains(str_replace(',', '', $normalized), mb_strtolower($student->last_name)));
    }
}
