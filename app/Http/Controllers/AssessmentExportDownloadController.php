<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssessmentExport;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AssessmentExportDownloadController extends Controller
{
    public function download(Request $request, AssessmentExport $assessmentExport, TenantContext $tenants): StreamedResponse
    {
        $this->authorizeDownload($request, $assessmentExport, $tenants);
        abort_unless($assessmentExport->status === 'completed' && $assessmentExport->output_path !== null, 404);
        $disk = $assessmentExport->output_disk ?? (string) config('assessment-exports.disk');
        abort_unless(Storage::disk($disk)->exists($assessmentExport->output_path), 404, 'Bulk assessment export not found.');

        return Storage::disk($disk)->download($assessmentExport->output_path, $assessmentExport->output_name ?? 'bulk-assessments.pdf');
    }

    public function report(Request $request, AssessmentExport $assessmentExport, TenantContext $tenants): StreamedResponse
    {
        $this->authorizeDownload($request, $assessmentExport, $tenants);
        abort_unless($assessmentExport->report_path !== null, 404);
        $disk = $assessmentExport->output_disk ?? (string) config('assessment-exports.disk');
        abort_unless(Storage::disk($disk)->exists($assessmentExport->report_path), 404, 'Skipped assessment report not found.');

        return Storage::disk($disk)->download($assessmentExport->report_path, 'skipped-assessments.csv');
    }

    private function authorizeDownload(Request $request, AssessmentExport $export, TenantContext $tenants): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null
            && $user->canAccessAdminPortal()
            && (int) $export->user_id === (int) $user->id
            && $tenants->getCurrentSchoolId() !== null
            && (int) $export->school_id === $tenants->getCurrentSchoolId(),
            404,
        );
    }
}
