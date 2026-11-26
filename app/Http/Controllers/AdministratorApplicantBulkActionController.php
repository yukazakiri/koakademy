<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApplicantCancellationReason;
use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Http\Requests\Administrators\ApplicantBulkActionRequest;
use App\Models\DocumentLocation;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\ApplicantApprovedForRequirements;
use App\Services\ApplicantCancellationService;
use App\Services\EnrollmentPipelineService;
use App\Services\EnrollmentService;
use App\Services\GeneralSettingsService;
use App\Services\StudentHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AdministratorApplicantBulkActionController extends Controller
{
    public function __construct(
        private readonly ApplicantCancellationService $applicantCancellation,
        private readonly EnrollmentPipelineService $enrollmentPipelineService,
        private readonly GeneralSettingsService $settingsService,
        private readonly StudentHoldService $studentHoldService,
    ) {}

    public function __invoke(ApplicantBulkActionRequest $request): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validated = $request->validated();
        $action = (string) $validated['action'];
        $ids = array_values(array_unique(array_map(intval(...), $validated['ids'])));

        $applicants = Student::query()
            ->with(['Course.department'])
            ->whereIn('id', $ids)
            ->get();

        $processed = 0;
        $skipped = 0;
        $errors = [];

        if ($action === 'notify_approval') {
            foreach ($applicants as $student) {
                if ($student->status !== StudentStatus::Applicant || $student->trashed() || blank($student->email)) {
                    $skipped++;

                    continue;
                }

                $student->notify(new ApplicantApprovedForRequirements(
                    senderName: $actor->name,
                    senderRole: $actor->role?->getLabel() ?? 'Administrator',
                ));
                $processed++;
            }

            $message = sprintf(
                'Queued approval notification for %d applicant%s%s.',
                $processed,
                $processed === 1 ? '' : 's',
                $skipped > 0 ? sprintf(' (%d skipped)', $skipped) : '',
            );
        } elseif ($action === 'set_scholarship') {
            $scholarshipEnum = isset($validated['scholarship_type']) && $validated['scholarship_type'] !== ''
                ? ScholarshipType::tryFrom((string) $validated['scholarship_type'])
                : null;
            $scholarshipValue = $scholarshipEnum && $scholarshipEnum !== ScholarshipType::None ? $scholarshipEnum->value : null;

            foreach ($applicants as $student) {
                if ($student->trashed()) {
                    $skipped++;

                    continue;
                }

                $student->update(['scholarship_type' => $scholarshipValue]);
                $processed++;
            }

            $message = $scholarshipEnum && $scholarshipEnum !== ScholarshipType::None
                ? sprintf('Tagged %d applicant%s with "%s"%s.', $processed, $processed === 1 ? '' : 's', $scholarshipEnum->getLabel(), $skipped > 0 ? sprintf(' (%d skipped)', $skipped) : '')
                : sprintf('Cleared scholarship tag on %d applicant%s%s.', $processed, $processed === 1 ? '' : 's', $skipped > 0 ? sprintf(' (%d skipped)', $skipped) : '');
        } elseif ($action === 'convert_to_enrollment') {
            $schoolYear = $this->settingsService->getCurrentSchoolYearString();
            $semester = $this->settingsService->getCurrentSemester();
            $pendingStatus = $this->enrollmentPipelineService->getPendingStatus();

            foreach ($applicants as $student) {
                if ($student->status !== StudentStatus::Applicant || $student->trashed() || ! $student->course_id) {
                    $skipped++;

                    continue;
                }

                $alreadyEnrolled = StudentEnrollment::query()
                    ->where('student_id', $student->id)
                    ->where('school_year', $schoolYear)
                    ->where('semester', $semester)
                    ->exists();

                if ($alreadyEnrolled) {
                    $skipped++;

                    continue;
                }

                if ($this->studentHoldService->blockingEnrollmentHoldsForStudent((int) $student->id)->isNotEmpty()) {
                    $skipped++;
                    $errors[] = sprintf('%s has an active blocking hold.', $student->full_name);

                    continue;
                }

                DB::transaction(function () use ($student, $schoolYear, $semester, $pendingStatus): void {
                    $yearLevel = max(1, (int) ($student->academic_year ?? 1));

                    $enrollment = StudentEnrollment::query()->create([
                        'student_id' => $student->id,
                        'course_id' => $student->course_id,
                        'status' => $pendingStatus,
                        'school_year' => $schoolYear,
                        'semester' => $semester,
                        'academic_year' => $yearLevel,
                        'downpayment' => 0,
                        'submission_channel' => 'administrator',
                        'workflow_runtime' => StudentEnrollment::WorkflowRuntimeLegacy,
                    ]);

                    $curriculumSubjects = Subject::query()
                        ->where('course_id', $student->course_id)
                        ->where('academic_year', $yearLevel)
                        ->where('semester', $semester)
                        ->get();

                    $subjectPayloads = [];

                    foreach ($curriculumSubjects as $subject) {
                        $lecRate = (float) ($student->Course?->lec_per_unit ?? 0);
                        $labRate = (float) ($student->Course?->lab_per_unit ?? 0);
                        $lectureFee = round(((int) ($subject->lecture ?? 0)) * $lecRate, 2);
                        $laboratoryFee = round(((int) ($subject->laboratory ?? 0)) * $labRate, 2);

                        $enrollment->subjectsEnrolled()->create([
                            'subject_id' => $subject->id,
                            'student_id' => $student->id,
                            'academic_year' => $yearLevel,
                            'school_year' => $schoolYear,
                            'semester' => $semester,
                            'enrolled_lecture_units' => (int) ($subject->lecture ?? 0),
                            'enrolled_laboratory_units' => (int) ($subject->laboratory ?? 0),
                            'lecture_fee' => $lectureFee,
                            'laboratory_fee' => $laboratoryFee,
                            'is_modular' => false,
                            'exclude_from_tuition' => false,
                        ]);

                        $subjectPayloads[] = [
                            'subject_id' => $subject->id,
                            'lecture_fee' => $lectureFee,
                            'laboratory_fee' => $laboratoryFee,
                            'is_modular' => false,
                            'exclude_from_tuition' => false,
                        ];
                    }

                    if (! $enrollment->studentTuition()->exists()) {
                        app(EnrollmentService::class)->createStudentTuition($enrollment, [
                            'subjectsEnrolled' => $subjectPayloads,
                            'discount' => 0,
                            'downpayment' => 0,
                            'additionalFees' => [],
                        ]);
                    }

                    $this->syncApplicantDocumentsToLocation($student);
                });

                $processed++;
            }

            $message = sprintf(
                'Converted %d applicant%s into %s (Sem %d) enrollments%s.',
                $processed,
                $processed === 1 ? '' : 's',
                $schoolYear,
                $semester,
                $skipped > 0 ? sprintf(' (%d skipped)', $skipped) : '',
            );
        } else {
            $reason = ApplicantCancellationReason::from((string) $validated['reason']);
            $note = isset($validated['note']) ? (string) $validated['note'] : null;
            $sendEmail = (bool) ($validated['send_email'] ?? true);

            foreach ($applicants as $student) {
                try {
                    $this->applicantCancellation->cancel(
                        student: $student,
                        actor: $actor,
                        reason: $reason,
                        note: $note,
                        sendEmail: $sendEmail,
                    );
                    $processed++;
                } catch (RuntimeException $exception) {
                    $skipped++;
                    $errors[] = sprintf('%s: %s', $student->full_name, $exception->getMessage());
                }
            }

            $message = sprintf(
                'Cancelled %d application%s%s.',
                $processed,
                $processed === 1 ? '' : 's',
                $skipped > 0 ? sprintf(' (%d skipped)', $skipped) : '',
            );
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'processed' => $processed,
                'skipped' => $skipped,
                'errors' => $errors,
                'message' => $message,
            ]);
        }

        return back()->with('flash', [
            $processed > 0 ? 'success' : 'error' => $processed > 0
                ? $message
                : ($errors[0] ?? 'No selected applicants were eligible for this bulk action.'),
        ]);
    }

    /**
     * Sync uploaded applicant documents stored in contacts['documents'] into DocumentLocation if missing.
     */
    private function syncApplicantDocumentsToLocation(Student $student): void
    {
        $contacts = is_array($student->contacts) ? $student->contacts : [];
        $documents = $contacts['documents'] ?? [];

        if (! is_array($documents) || $documents === []) {
            return;
        }

        $typeMap = [
            'birth_certificate' => 'birth_certificate',
            'psa_birth_certificate' => 'birth_certificate',
            'nso_birth_certificate' => 'birth_certificate',
            'form_138' => 'form_138',
            'report_card' => 'form_138',
            'high_school_report_card' => 'form_138',
            'form_137' => 'form_137',
            'high_school_diploma' => 'form_137',
            'good_moral' => 'good_moral_cert',
            'good_moral_cert' => 'good_moral_cert',
            'good_moral_certificate' => 'good_moral_cert',
            'certificate_of_good_moral' => 'good_moral_cert',
            'transfer_credentials' => 'transfer_credentials',
            'honorable_dismissal' => 'transfer_credentials',
            'transcript_records' => 'transcript_records',
            'transcript_of_records' => 'transcript_records',
            'photo' => 'picture_1x1',
            'profile_photo' => 'picture_1x1',
            'picture_1x1' => 'picture_1x1',
            '1x1_photo' => 'picture_1x1',
            '2x2_photo' => 'picture_1x1',
            'id_photo' => 'picture_1x1',
        ];

        $mappedAttributes = [];
        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $rawType = mb_strtolower(mb_trim((string) ($document['type'] ?? '')));
            $path = $document['path'] ?? null;

            if ($rawType === '' || ! is_string($path) || mb_trim($path) === '') {
                continue;
            }

            $column = $typeMap[$rawType] ?? null;
            if ($column !== null && ! isset($mappedAttributes[$column])) {
                $mappedAttributes[$column] = $path;
            }
        }

        if ($mappedAttributes === []) {
            return;
        }

        $student->loadMissing('DocumentLocation');
        if (! $student->DocumentLocation) {
            $docLocation = DocumentLocation::create($mappedAttributes);
            $student->forceFill(['document_location_id' => $docLocation->id])->save();
        } else {
            $updates = [];
            foreach ($mappedAttributes as $column => $path) {
                $existing = $student->DocumentLocation->getAttribute($column);
                if (! is_string($existing) || mb_trim($existing) === '') {
                    $updates[$column] = $path;
                }
            }

            if ($updates !== []) {
                $student->DocumentLocation->update($updates);
            }
        }
    }
}
