<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentClassWaitlist;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\EnrollmentWaitlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class AdministratorEnrollmentWaitlistController extends Controller
{
    public function __construct(
        private readonly EnrollmentWaitlistService $waitlistService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/login');
        }

        $search = mb_trim((string) $request->query('search', ''));
        $courseId = $request->filled('course_id') && $request->query('course_id') !== 'all'
            ? (int) $request->query('course_id')
            : null;

        $snapshot = $this->waitlistService->queueSnapshot(
            search: $search !== '' ? $search : null,
            courseId: $courseId,
        );

        return Inertia::render('administrators/enrollments/waitlists', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'term' => $snapshot['term'],
            'unassigned' => $snapshot['unassigned'],
            'waitlists' => $snapshot['waitlists'],
            'stats' => $snapshot['stats'],
            'courses' => Course::query()->orderBy('code')->get(['id', 'code', 'title']),
            'filters' => [
                'search' => $search,
                'course_id' => $courseId ? (string) $courseId : 'all',
            ],
            'flash' => session('flash'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->canManageWaitlists($actor), 403);

        $validated = $request->validate([
            'subject_enrollment_id' => ['required', 'integer', 'exists:subject_enrollments,id'],
            'preferred_class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $subjectEnrollment = SubjectEnrollment::query()->findOrFail((int) $validated['subject_enrollment_id']);
        $entry = $this->waitlistService->addToWaitlist(
            subjectEnrollment: $subjectEnrollment,
            actor: $actor,
            preferredClassId: isset($validated['preferred_class_id']) ? (int) $validated['preferred_class_id'] : null,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );

        return back()->with('flash', [
            'success' => sprintf('Added student to waitlist at position #%d.', $entry->position),
        ]);
    }

    public function assign(Request $request, SubjectEnrollment $subjectEnrollment): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->canManageWaitlists($actor), 403);

        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'force_overload' => ['boolean'],
        ]);

        $class = Classes::query()->findOrFail((int) $validated['class_id']);

        try {
            $this->waitlistService->assignClass(
                $subjectEnrollment,
                $class,
                $actor,
                (bool) ($validated['force_overload'] ?? false),
            );

            return back()->with('flash', [
                'success' => sprintf('Assigned student to Section %s (%s).', $class->section, $class->subject_code),
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()]);
        }
    }

    public function promote(Request $request, EnrollmentClassWaitlist $waitlist): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->canManageWaitlists($actor), 403);

        $validated = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'force_overload' => ['boolean'],
        ]);

        try {
            $updated = $this->waitlistService->promoteWaitlist(
                $waitlist,
                $actor,
                isset($validated['class_id']) ? (int) $validated['class_id'] : null,
                (bool) ($validated['force_overload'] ?? false),
            );

            return back()->with('flash', [
                'success' => sprintf('Promoted student from waitlist into Section %s.', $updated->promotedClass?->section ?? 'assigned'),
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()]);
        }
    }

    public function destroy(EnrollmentClassWaitlist $waitlist): RedirectResponse
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $this->canManageWaitlists($actor), 403);

        $this->waitlistService->cancelWaitlist($waitlist);

        return back()->with('flash', ['success' => 'Waitlist entry cancelled.']);
    }

    public function autoAssign(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->canManageWaitlists($actor), 403);

        $validated = $request->validate([
            'subject_enrollment_ids' => ['nullable', 'array'],
            'subject_enrollment_ids.*' => ['integer', 'exists:subject_enrollments,id'],
            'waitlist_when_full' => ['boolean'],
        ]);

        $summary = $this->waitlistService->bulkAutoAssign(
            actor: $actor,
            subjectEnrollmentIds: $validated['subject_enrollment_ids'] ?? null,
            waitlistWhenFull: (bool) ($validated['waitlist_when_full'] ?? true),
        );

        return back()->with('flash', [
            'success' => sprintf(
                'Auto-assign finished: %d assigned to open sections, %d added to waitlist%s.',
                $summary['assigned_count'],
                $summary['waitlisted_count'],
                $summary['skipped_count'] > 0 ? sprintf(', %d skipped (no classes offered)', $summary['skipped_count']) : '',
            ),
        ]);
    }

    private function canManageWaitlists(User $user): bool
    {
        if ($user->can('Update:StudentEnrollment') || $user->hasRole('super_admin')) {
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
