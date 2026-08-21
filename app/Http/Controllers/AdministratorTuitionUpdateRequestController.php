<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\ClaimStudentTuitionUpdateRequest;
use App\Http\Requests\Administrators\RejectStudentTuitionUpdateRequest;
use App\Http\Requests\Administrators\ResolveStudentTuitionUpdateAdjustmentRequest;
use App\Http\Requests\Administrators\ResolveStudentTuitionUpdatePaymentRequest;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\Transaction;
use App\Models\TuitionAdjustment;
use App\Models\User;
use App\Services\StudentTuitionUpdateRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorTuitionUpdateRequestController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view_tuition_fees'), 403);
        $search = mb_strtolower(mb_trim($request->string('search')->toString()));
        $query = StudentTuitionUpdateRequest::query()
            ->with(['student:id,student_id,first_name,middle_name,last_name', 'reviewer:id,name'])
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')->toString()))
            ->when($request->filled('concern_type'), fn ($builder) => $builder->where('concern_type', $request->string('concern_type')->toString()))
            ->when($request->filled('school_year'), fn ($builder) => $builder->where('school_year', $request->string('school_year')->toString()))
            ->when($request->filled('semester'), fn ($builder) => $builder->where('semester', $request->integer('semester')))
            ->when($search !== '', function ($builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where(function ($nested) use ($like): void {
                    $nested->whereRaw('lower(receipt_number) like ?', [$like])
                        ->orWhereHas('student', function ($students) use ($like): void {
                            $students->whereRaw('cast(student_id as text) like ?', [$like])
                                ->orWhereRaw('lower(first_name) like ?', [$like])
                                ->orWhereRaw('lower(last_name) like ?', [$like]);
                        });
                });
            })
            ->latest('created_at')->latest('id');

        $requests = $query->paginate(20)->withQueryString();
        $requests->getCollection()->transform(fn (StudentTuitionUpdateRequest $tuitionRequest): array => $this->serialize($tuitionRequest));

        return Inertia::render('administrators/finance/tuition-update-requests/index', [
            'requests' => $requests,
            'filters' => $request->only(['status', 'concern_type', 'school_year', 'semester', 'search']),
            'can_manage' => $request->user()?->can('manage_tuition_fees') ?? false,
            'statuses' => [
                StudentTuitionUpdateRequest::StatusPending,
                StudentTuitionUpdateRequest::StatusInReview,
                StudentTuitionUpdateRequest::StatusResolved,
                StudentTuitionUpdateRequest::StatusRejected,
            ],
            'concerns' => StudentTuitionUpdateRequest::concernTypes(),
        ]);
    }

    public function show(Request $request, StudentTuitionUpdateRequest $tuitionUpdateRequest): Response
    {
        abort_unless($request->user()?->can('view_tuition_fees'), 403);
        $tuitionUpdateRequest->load([
            'student:id,student_id,first_name,middle_name,last_name,email', 'enrollment:id,student_id,school_year,semester',
            'tuition:id,enrollment_id,overall_tuition,total_balance,discount', 'reviewer:id,name',
            'resolvedTransaction:id,transaction_number,invoicenumber,status,transaction_date',
            'tuitionAdjustment:id,reason,created_at', 'events.actor:id,name',
        ]);

        $matchingTransactions = Transaction::query()
            ->select(['id', 'transaction_number', 'invoicenumber', 'status', 'transaction_date', 'settlements'])
            ->whereHas('studentTransactions', function ($transactions) use ($tuitionUpdateRequest): void {
                $transactions->where('student_id', $tuitionUpdateRequest->student_id)
                    ->when($tuitionUpdateRequest->student_enrollment_id !== null, fn ($query) => $query->where('student_enrollment_id', $tuitionUpdateRequest->student_enrollment_id));
            })
            ->when($tuitionUpdateRequest->receipt_number !== null, fn ($query) => $query->where('invoicenumber', $tuitionUpdateRequest->receipt_number))
            ->whereIn('status', ['Paid', 'Completed', 'paid', 'completed'])
            ->latest('transaction_date')->latest('id')
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'receipt_number' => $transaction->invoicenumber,
                'status' => $transaction->status,
                'amount' => (float) $transaction->raw_total_amount,
                'date' => $transaction->transaction_date?->toIso8601String(),
            ]);
        $matchingAdjustments = TuitionAdjustment::query()
            ->where('student_enrollment_id', $tuitionUpdateRequest->student_enrollment_id)
            ->where('student_tuition_id', $tuitionUpdateRequest->student_tuition_id)
            ->latest('created_at')->limit(20)->get()
            ->map(fn (TuitionAdjustment $adjustment): array => [
                'id' => $adjustment->id,
                'reason' => $adjustment->reason,
                'created_at' => $adjustment->created_at?->toIso8601String(),
            ]);

        return Inertia::render('administrators/finance/tuition-update-requests/show', [
            'request' => $this->serialize($tuitionUpdateRequest, true),
            'matching_transactions' => $matchingTransactions,
            'matching_adjustments' => $matchingAdjustments,
            'can_manage' => $request->user()?->can('manage_tuition_fees') ?? false,
            'is_current_reviewer' => $tuitionUpdateRequest->reviewed_by_user_id === $request->user()?->id,
        ]);
    }

    public function claim(ClaimStudentTuitionUpdateRequest $request, StudentTuitionUpdateRequest $tuitionUpdateRequest, StudentTuitionUpdateRequestService $requests): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $requests->claim($tuitionUpdateRequest, $user);

        return back()->with('flash', ['success' => 'Request claimed for review.']);
    }

    public function resolvePayment(ResolveStudentTuitionUpdatePaymentRequest $request, StudentTuitionUpdateRequest $tuitionUpdateRequest, StudentTuitionUpdateRequestService $requests): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $transaction = Transaction::query()->findOrFail($request->integer('transaction_id'));
        $requests->resolveWithPayment($tuitionUpdateRequest, $transaction, $user, $request->string('resolution_note')->toString());

        return back()->with('flash', ['success' => 'Request resolved and linked to the verified payment.']);
    }

    public function resolveAdjustment(ResolveStudentTuitionUpdateAdjustmentRequest $request, StudentTuitionUpdateRequest $tuitionUpdateRequest, StudentTuitionUpdateRequestService $requests): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $adjustment = TuitionAdjustment::query()->findOrFail($request->integer('tuition_adjustment_id'));
        $requests->resolveWithAdjustment($tuitionUpdateRequest, $adjustment, $user, $request->string('resolution_note')->toString());

        return back()->with('flash', ['success' => 'Request resolved and linked to the tuition adjustment.']);
    }

    public function reject(RejectStudentTuitionUpdateRequest $request, StudentTuitionUpdateRequest $tuitionUpdateRequest, StudentTuitionUpdateRequestService $requests): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $requests->reject($tuitionUpdateRequest, $user, $request->string('resolution_note')->toString());

        return back()->with('flash', ['success' => 'Request rejected and the student was notified.']);
    }

    /** @return array<string, mixed> */
    private function serialize(StudentTuitionUpdateRequest $request, bool $withTimeline = false): array
    {
        $data = [
            'id' => $request->id,
            'enrollment_id' => $request->student_enrollment_id,
            'student' => [
                'id' => $request->student?->id,
                'student_number' => (string) ($request->student?->student_id ?? ''),
                'name' => $request->student?->full_name,
                'email' => $request->student?->email,
            ],
            'school_year' => $request->school_year,
            'semester' => $request->semester,
            'concern_type' => $request->concern_type,
            'receipt_number' => $request->receipt_number,
            'details' => $request->details,
            'status' => $request->status,
            'resolution_note' => $request->resolution_note,
            'reviewer_name' => $request->reviewer?->name,
            'submitted_at' => $request->created_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'resolved_at' => $request->resolved_at?->toIso8601String(),
            'tuition' => $request->tuition ? [
                'overall_tuition' => (float) $request->tuition->overall_tuition,
                'balance' => (float) $request->tuition->total_balance,
                'discount' => (int) $request->tuition->discount,
            ] : null,
            'resolved_transaction' => $request->resolvedTransaction ? [
                'id' => $request->resolvedTransaction->id,
                'receipt_number' => $request->resolvedTransaction->invoicenumber,
            ] : null,
            'tuition_adjustment' => $request->tuitionAdjustment ? [
                'id' => $request->tuitionAdjustment->id,
                'reason' => $request->tuitionAdjustment->reason,
            ] : null,
        ];

        if ($withTimeline) {
            $data['events'] = $request->events->map(fn ($event): array => [
                'id' => $event->id,
                'event' => $event->event,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'note' => $event->note,
                'actor_name' => $event->actor?->name,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->all();
        }

        return $data;
    }
}
