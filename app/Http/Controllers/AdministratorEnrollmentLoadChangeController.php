<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Administrators\StoreEnrollmentLoadChangeRequest;
use App\Models\EnrollmentLoadChange;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentLoadChangeService;
use App\Services\EnrollmentPeriodService;
use App\Services\StudentHoldService;
use App\Settings\SiteSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class AdministratorEnrollmentLoadChangeController extends Controller
{
    public function __construct(
        private readonly EnrollmentLoadChangeService $loadChangeService,
        private readonly EnrollmentPeriodService $periodService,
        private readonly StudentHoldService $holdService,
    ) {}

    public function index(StudentEnrollment $enrollment): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageLoadChange($user, $enrollment), 403);

        $enrollment->loadMissing(['course', 'subjectsEnrolled.subject', 'subjectsEnrolled.class', 'studentTuition']);

        $history = EnrollmentLoadChange::query()
            ->with(['requestedBy:id,name', 'approvedBy:id,name', 'rejectedBy:id,name', 'addedClass:id,section,subject_code'])
            ->where('student_enrollment_id', $enrollment->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EnrollmentLoadChange $item): array => $this->loadChangeService->serialize($item))
            ->values();

        $enrolledSubjectIds = $enrollment->subjectsEnrolled->pluck('subject_id')->filter()->all();
        $availableSubjects = $enrollment->course_id
            ? Subject::query()
                ->where('course_id', $enrollment->course_id)
                ->when($enrolledSubjectIds !== [], fn ($q) => $q->whereNotIn('id', $enrolledSubjectIds))
                ->orderBy('academic_year')
                ->orderBy('semester')
                ->orderBy('code')
                ->limit(120)
                ->get(['id', 'code', 'title', 'lecture', 'laboratory'])
                ->map(fn (Subject $s): array => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'title' => $s->title,
                    'units' => (int) ($s->lecture ?? 0) + (int) ($s->laboratory ?? 0),
                ])
                ->values()
            : collect();

        $period = $this->periodService->describe($enrollment->school_year, $enrollment->semester);
        $blockingHolds = $enrollment->student_id
            ? $this->holdService->blockingAddDropHoldsForStudent((int) $enrollment->student_id)->count()
            : 0;

        return response()->json([
            'course_id' => $enrollment->course_id,
            'load_changes' => $history,
            'enrolled_subjects' => $enrollment->subjectsEnrolled->map(fn ($se): array => [
                'id' => $se->id,
                'subject_id' => $se->subject_id,
                'code' => $se->subject?->code ?? $se->external_subject_code ?? 'Subject',
                'title' => $se->subject?->title ?? $se->external_subject_title ?? 'Untitled',
                'section' => $se->class?->section,
                'units' => (int) ($se->enrolled_lecture_units ?? $se->subject?->lecture ?? 0) + (int) ($se->enrolled_laboratory_units ?? $se->subject?->laboratory ?? 0),
            ])->values(),
            'available_subjects' => $availableSubjects,
            'window' => [
                'add_drop_open' => (bool) $period['add_drop_open'],
                'enforce_windows' => (bool) $period['enforce_windows'],
                'add_drop_deadline_at' => $period['add_drop_deadline_at'],
            ],
            'blocking_holds_count' => $blockingHolds,
        ]);
    }

    public function preview(StoreEnrollmentLoadChangeRequest $request, StudentEnrollment $enrollment): JsonResponse
    {
        return response()->json(
            $this->loadChangeService->preview($enrollment, $request->validated()),
        );
    }

    public function store(StoreEnrollmentLoadChangeRequest $request, StudentEnrollment $enrollment): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validated = $request->validated();
        if (blank($validated['reason'] ?? null)) {
            return response()->json(['message' => 'A reason is required for a Change-of-Load request.'], 422);
        }

        try {
            $change = $this->loadChangeService->submit($enrollment, $validated, $actor);

            return response()->json([
                'load_change' => $this->loadChangeService->serialize($change),
                'message' => $change->status === EnrollmentLoadChange::StatusApproved
                    ? sprintf('Change-of-Load %s approved and applied.', $change->reference_number)
                    : sprintf('Change-of-Load %s queued for approval.', $change->reference_number),
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function approve(Request $request, StudentEnrollment $enrollment, EnrollmentLoadChange $loadChange): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->canManageLoadChange($actor, $enrollment), 403);
        abort_if($loadChange->student_enrollment_id !== $enrollment->id, 404);

        try {
            $updated = $this->loadChangeService->approve(
                $loadChange,
                $actor,
                $request->boolean('force_overload', false),
                $request->boolean('confirm_grade_loss', false),
            );

            return response()->json([
                'load_change' => $this->loadChangeService->serialize($updated),
                'message' => sprintf('Change-of-Load %s approved and tuition updated.', $updated->reference_number),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reject(Request $request, StudentEnrollment $enrollment, EnrollmentLoadChange $loadChange): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->canManageLoadChange($actor, $enrollment), 403);
        abort_if($loadChange->student_enrollment_id !== $enrollment->id, 404);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $updated = $this->loadChangeService->reject($loadChange, $actor, (string) $validated['rejection_reason']);

            return response()->json([
                'load_change' => $this->loadChangeService->serialize($updated),
                'message' => sprintf('Change-of-Load %s rejected.', $updated->reference_number),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function slip(StudentEnrollment $enrollment, EnrollmentLoadChange $loadChange): View
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $this->canManageLoadChange($actor, $enrollment), 403);
        abort_if($loadChange->student_enrollment_id !== $enrollment->id, 404);

        $enrollment->loadMissing(['student', 'course.department', 'studentTuition']);
        $loadChange->loadMissing(['addedClass', 'requestedBy', 'approvedBy']);

        $branding = app(SiteSettings::class)->getBrandingArray();

        return view('enrollments.change-of-load-slip', [
            'organizationName' => (string) ($branding['organizationName'] ?? config('app.name')),
            'currency' => (string) ($branding['currency'] ?? 'PHP'),
            'enrollment' => $enrollment,
            'loadChange' => $loadChange,
        ]);
    }

    private function canManageLoadChange(User $user, StudentEnrollment $enrollment): bool
    {
        if ($user->can('update', $enrollment) || $user->can('Update:StudentEnrollment')) {
            return true;
        }

        return in_array($user->role, [
            UserRole::Developer,
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::Registrar,
            UserRole::AssistantRegistrar,
            UserRole::Dean,
            UserRole::AssociateDean,
            UserRole::DepartmentHead,
            UserRole::ProgramChair,
            UserRole::Cashier,
            UserRole::AccountingOfficer,
            UserRole::BursarOfficer,
        ], true);
    }
}
