<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Enrollment\TransitionResult;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Enrollment\Exceptions\EnrollmentTransitionException;
use App\Enrollment\LegacyEnrollmentWorkflowAdapter;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Backwards-compatible application facade.
 *
 * Workflow state changes are delegated to EnrollmentWorkflowCoordinator. The
 * legacy adapter remains available only for enrollments pinned to the legacy
 * runtime while callers are migrated to the typed submission API.
 */
final readonly class EnrollmentService
{
    public function __construct(
        private EnrollmentWorkflowCoordinator $workflow,
        private LegacyEnrollmentWorkflowAdapter $legacy,
    ) {}

    /**
     * @param  Collection<int, mixed>  $classes
     * @param  Collection<int, mixed>  $enrolledSubjectsData
     * @return Collection<int, mixed>
     */
    public function checkFullClasses(Collection $classes, Collection $enrolledSubjectsData): Collection
    {
        return $this->legacy->checkFullClasses($classes, $enrolledSubjectsData);
    }

    /** @param array<string, mixed> $formData */
    public function createStudentTuition(StudentEnrollment $enrollment, array $formData): ?StudentTuition
    {
        return $this->legacy->createStudentTuition($enrollment, $formData);
    }

    public function verifyByHeadDept(StudentEnrollment $enrollment, ?User $actor = null): bool
    {
        return $this->workflow->verifyAcademic($enrollment, $this->authenticatedActor($actor))->successful;
    }

    /** @param array<string, mixed> $actionData */
    public function verifyByCashier(StudentEnrollment $enrollment, array $actionData, ?User $actor = null): bool
    {
        return $this->workflow->verifyPayment($enrollment, $this->authenticatedActor($actor), $actionData)->successful;
    }

    /** @param array<string, mixed> $actionData */
    public function verifyByCashierWithoutReceipt(StudentEnrollment $enrollment, array $actionData, ?User $actor = null): bool
    {
        $payload = [
            ...$actionData,
            'without_receipt' => true,
            'reason' => $actionData['reason'] ?? $actionData['remarks'] ?? null,
        ];

        return $this->workflow->verifyPayment($enrollment, $this->authenticatedActor($actor), $payload)->successful;
    }

    public function undoHeadDeptVerification(StudentEnrollment $enrollment, ?User $actor = null): bool
    {
        return $this->workflow->reopen(
            $enrollment,
            $this->optionalAuthenticatedActor($actor),
            null,
            'Academic verification correction.',
        )->successful;
    }

    public function undoCashierVerification(int $enrollmentRecordId, ?User $actor = null): bool
    {
        $enrollment = StudentEnrollment::withTrashed()->findOrFail($enrollmentRecordId);

        return $this->workflow->reopen(
            $enrollment,
            $this->optionalAuthenticatedActor($actor),
            null,
            'Cashier verification correction.',
        )->successful;
    }

    /** @return array<string, mixed> */
    public function resendAssessmentNotification(StudentEnrollment $enrollment): array
    {
        return $this->legacy->resendAssessmentNotification($enrollment);
    }

    /** @return array<int, array{label:string, disabled:bool}> */
    public function getSubjectDropdownOptions(
        ?int $courseId = null,
        ?int $studentId = null,
        string|int|null $semester = null,
        ?string $schoolYear = null,
    ): array {
        return $this->legacy->getSubjectDropdownOptions($courseId, $studentId, $semester, $schoolYear);
    }

    /** @param array<string, mixed> $payload */
    public function transition(
        StudentEnrollment $enrollment,
        User $actor,
        ?string $transitionKey,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): TransitionResult {
        return $this->workflow->transition($enrollment, $actor, $transitionKey, $payload, $idempotencyKey);
    }

    private function authenticatedActor(?User $actor): User
    {
        $actor ??= $this->optionalAuthenticatedActor();
        if (! $actor instanceof User) {
            throw new EnrollmentTransitionException('An authenticated enrollment actor is required.');
        }

        return $actor;
    }

    private function optionalAuthenticatedActor(?User $actor = null): ?User
    {
        if ($actor instanceof User) {
            return $actor;
        }

        $authenticated = auth()->user();

        return $authenticated instanceof User ? $authenticated : null;
    }
}
