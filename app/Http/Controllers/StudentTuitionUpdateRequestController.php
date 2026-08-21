<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentTuitionUpdateRequest;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\StudentTuitionUpdateRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentTuitionUpdateRequestController extends Controller
{
    public function index(Request $request, StudentTuitionUpdateRequestService $requests, GeneralSettingsService $settings): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $student = $requests->studentFor($user);
        $selectedPeriod = [
            'school_year' => $request->string('school_year')->toString() ?: $settings->getCurrentSchoolYearString(),
            'semester' => $request->integer('semester', $settings->getCurrentSemester()),
        ];

        if ($student === null) {
            return Inertia::render('student/tuition/update-requests', [
                'requests' => [], 'periods' => [], 'selected_period' => $selectedPeriod,
                'concerns' => $this->concerns(), 'error' => 'No student record was found. Please contact the registrar.',
            ]);
        }

        $periods = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->select(['school_year', 'semester'])
            ->get()
            ->merge(StudentTuition::query()->where('student_id', $student->id)->select(['school_year', 'semester'])->get())
            ->unique(fn ($record): string => $record->school_year.'|'.$record->semester)
            ->sortByDesc(fn ($record): string => $record->school_year.'|'.$record->semester)
            ->values()
            ->map(fn ($record): array => [
                'school_year' => $record->school_year,
                'semester' => (int) $record->semester,
                'label' => $record->school_year.' · '.((int) $record->semester === 1 ? '1st Semester' : '2nd Semester'),
            ]);

        return Inertia::render('student/tuition/update-requests', [
            'requests' => StudentTuitionUpdateRequest::query()
                ->where('student_id', $student->id)
                ->with(['reviewer:id,name'])
                ->latest('created_at')->latest('id')
                ->get()
                ->map(fn (StudentTuitionUpdateRequest $tuitionRequest): array => $this->serialize($tuitionRequest)),
            'periods' => $periods,
            'selected_period' => $selectedPeriod,
            'concerns' => $this->concerns(),
            'error' => null,
        ]);
    }

    public function store(StoreStudentTuitionUpdateRequest $request, StudentTuitionUpdateRequestService $requests): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $requests->submit($user, $request->validated());

        return back()->with('flash', ['success' => 'Your tuition update request was submitted to Finance.']);
    }

    /** @return list<array{value: string, label: string, description: string}> */
    private function concerns(): array
    {
        return [
            ['value' => StudentTuitionUpdateRequest::ConcernMissingPayment, 'label' => 'Payment not reflected', 'description' => 'You paid an installment or downpayment, but it is not yet reflected.'],
            ['value' => StudentTuitionUpdateRequest::ConcernDiscount, 'label' => 'Discount concern', 'description' => 'A discount is missing or the displayed discount is incorrect.'],
            ['value' => StudentTuitionUpdateRequest::ConcernSubjectChange, 'label' => 'Subject enrollment change', 'description' => 'A subject was added, removed, or is affecting your assessment incorrectly.'],
            ['value' => StudentTuitionUpdateRequest::ConcernOther, 'label' => 'Other tuition concern', 'description' => 'Tell Finance about another tuition or assessment issue.'],
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(StudentTuitionUpdateRequest $request): array
    {
        return [
            'id' => $request->id,
            'school_year' => $request->school_year,
            'semester' => $request->semester,
            'concern_type' => $request->concern_type,
            'receipt_number' => $request->receipt_number,
            'details' => $request->details,
            'status' => $request->status,
            'resolution_note' => $request->resolution_note,
            'reviewer_name' => $request->reviewer?->name,
            'submitted_at' => $request->created_at?->toIso8601String(),
            'resolved_at' => $request->resolved_at?->toIso8601String(),
        ];
    }
}
