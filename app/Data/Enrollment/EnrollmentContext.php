<?php

declare(strict_types=1);

namespace App\Data\Enrollment;

use App\Enums\StudentType;
use App\Models\StudentEnrollment;
use App\Models\User;

final readonly class EnrollmentContext
{
    /** @param array<string, mixed> $facts */
    public function __construct(
        public ?int $schoolId,
        public ?string $studentType,
        public ?int $courseId,
        public ?string $schoolYear,
        public ?int $semester,
        public ?int $yearLevel = null,
        public string $channel = 'administrator',
        public ?StudentEnrollment $enrollment = null,
        public array $facts = [],
        public ?User $actor = null,
        public array $pinnedPolicyConfiguration = [],
    ) {}

    /** @param array<string, mixed> $facts */
    public static function fromEnrollment(
        StudentEnrollment $enrollment,
        ?string $channel = null,
        ?User $actor = null,
        array $facts = [],
    ): self {
        $enrollment->loadMissing('student', 'policySnapshot');
        $student = $enrollment->student()->first();
        $snapshot = $enrollment->policySnapshot()->first();
        $studentType = $student?->student_type;
        $studentType = $studentType instanceof StudentType
            ? $studentType->value
            : (is_string($studentType) ? $studentType : null);
        $pinnedPolicyConfiguration = $snapshot?->configuration;

        return new self(
            schoolId: $enrollment->school_id === null ? null : (int) $enrollment->school_id,
            studentType: $studentType,
            courseId: $enrollment->course_id === null ? null : (int) $enrollment->course_id,
            schoolYear: $enrollment->school_year,
            semester: $enrollment->semester,
            yearLevel: $enrollment->academic_year,
            channel: $channel ?? (string) ($enrollment->submission_channel ?: 'administrator'),
            enrollment: $enrollment,
            facts: $facts,
            actor: $actor,
            pinnedPolicyConfiguration: is_array($pinnedPolicyConfiguration) ? $pinnedPolicyConfiguration : [],
        );
    }
}
