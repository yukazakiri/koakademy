<?php

declare(strict_types=1);

namespace App\Contracts\Enrollment;

use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;

interface RuntimeEnrollmentAssignmentStrategy extends EnrollmentAssignmentStrategy
{
    /** @param array<string, mixed> $configuration */
    public function execute(EnrollmentContext $context, array $configuration, string $idempotencyKey): ActionResult;
}
