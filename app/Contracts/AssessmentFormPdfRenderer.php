<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\StudentEnrollment;

interface AssessmentFormPdfRenderer
{
    public function render(StudentEnrollment $enrollment, string $outputPath): void;
}
