<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\FacultyBulkImportTemplateExport;
use App\Http\Requests\Administrators\ConfirmFacultyBulkImportRequest;
use App\Http\Requests\Administrators\StoreFacultyBulkImportRequest;
use App\Models\Faculty;
use App\Models\FacultyBulkImport;
use App\Models\User;
use App\Services\FacultyBulkImportService;
use App\Services\FacultyCustomFieldDefinitionService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AdministratorFacultyImportController extends Controller
{
    public function downloadTemplate(
        TenantContext $tenantContext,
        FacultyCustomFieldDefinitionService $definitions,
    ): BinaryFileResponse {
        Gate::authorize('viewAny', Faculty::class);
        $schoolId = $tenantContext->getCurrentSchoolId();
        abort_if($schoolId === null, 422, 'Choose an active school before downloading a faculty template.');

        return Excel::download(
            new FacultyBulkImportTemplateExport($definitions->activeForSchool($schoolId)),
            'faculty-import-template.xlsx',
        );
    }

    public function store(StoreFacultyBulkImportRequest $request, FacultyBulkImportService $imports): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');
        abort_unless($user instanceof User && $file !== null, 422);
        $import = $imports->stage($user, $file);

        return response()->json(['import' => $imports->serialize($import)], 201);
    }

    public function confirm(
        ConfirmFacultyBulkImportRequest $request,
        FacultyBulkImport $facultyBulkImport,
        FacultyBulkImportService $imports,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        /** @var list<int> $rowIds */
        $rowIds = $request->validated('row_ids');
        $import = $imports->confirm($facultyBulkImport, $user, $rowIds);

        return response()->json(['import' => $imports->serialize($import)]);
    }
}
