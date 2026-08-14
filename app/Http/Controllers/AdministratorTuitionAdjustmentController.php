<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Http\Requests\Administrators\ResolveTuitionAdjustmentRowsRequest;
use App\Http\Requests\Administrators\StoreTuitionAdjustmentBatchRequest;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\TuitionAdjustment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\TuitionAdjustmentNotificationService;
use App\Services\TuitionAdjustmentService;
use App\Services\TuitionPaymentScheduleSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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
