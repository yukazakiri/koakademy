<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AssessmentFormPdfRenderer;
use App\Models\StudentEnrollment;

final readonly class LaravelAssessmentFormPdfRenderer implements AssessmentFormPdfRenderer
{
    public function __construct(
        private AssessmentFormDataService $formData,
        private PdfGenerationService $pdfs,
    ) {}

    public function render(StudentEnrollment $enrollment, string $outputPath): void
    {
        $this->pdfs->generatePdfFromView('pdf.assesment-form', $this->formData->buildViewData($enrollment), $outputPath, [
            'landscape' => true,
            'print-background' => true,
        ]);
    }
}
