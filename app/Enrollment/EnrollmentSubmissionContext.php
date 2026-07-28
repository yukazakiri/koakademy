<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\EnrollmentSubmissionData;
use Closure;

final class EnrollmentSubmissionContext
{
    private ?EnrollmentSubmissionData $submission = null;

    public function current(): ?EnrollmentSubmissionData
    {
        return $this->submission;
    }

    public function run(EnrollmentSubmissionData $submission, Closure $callback): mixed
    {
        $previous = $this->submission;
        $this->submission = $submission;

        try {
            return $callback();
        } finally {
            $this->submission = $previous;
        }
    }
}
