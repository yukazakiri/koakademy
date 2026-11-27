<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\DocumentLocation;
use App\Models\Resource;
use App\Models\Student;
use App\Models\User;
use App\Services\IdentifierGenerator;
use App\Services\StudentConfigurationService;
use App\Services\StudentDocumentService;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdministratorStudentDocumentController extends Controller
{
    public function __construct(
        private readonly StudentDocumentService $documentService,
        private readonly StudentConfigurationService $configuration,
    ) {}

    public function listAll(Request $request): Response
    {
        $this->authorizeDocumentAccess(['ViewAny:Student', 'View:Student']);

        $search = is_string($request->input('search')) ? mb_trim($request->input('search')) : '';
        $compliance = $request->string('compliance', 'all')->toString();
        if (! in_array($compliance, ['all', 'pending_review', 'missing', 'rejected', 'complete'], true)) {
            $compliance = 'all';
        }
        $missingDoc = is_string($request->input('missing_doc')) ? mb_trim($request->input('missing_doc')) : '';
        if ($missingDoc !== '' && ! in_array($missingDoc, DocumentLocation::DOCUMENT_FIELDS, true)) {
            $missingDoc = '';
        }
        $studentType = is_string($request->input('student_type')) ? mb_trim($request->input('student_type')) : '';
        $courseId = $request->integer('course_id');
        $courseId = $courseId > 0 ? $courseId : null;
        $yearLevel = $request->integer('year_level');
        $yearLevel = $yearLevel > 0 ? $yearLevel : null;
        $viewMode = $request->string('view', 'matrix')->toString();
        if (! in_array($viewMode, ['matrix', 'workbench'], true)) {
            $viewMode = 'matrix';
        }

        $sort = $request->string('sort', 'name')->toString();
        $allowedSorts = ['name', 'student_id', 'student_type', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'name';
        $direction = $request->string('direction', 'asc')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page', 15);
        $perPage = in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15;

        $schoolId = $this->configuration->schoolId();
        $matrixColumns = $this->documentService->matrixColumnsForSchool($schoolId, $studentType !== '' ? $studentType : null);
        $hasDocRecordsTable = Schema::hasTable('student_document_records');

        $scopeQuery = Student::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = "%{$search}%";
                $query->where(function (Builder $q) use ($like): void {
                    $q->where('students.first_name', 'like', $like)
                        ->orWhere('students.last_name', 'like', $like)
                        ->orWhere('students.student_id', 'like', $like)
                        ->orWhere('students.lrn', 'like', $like)
                        ->orWhere('students.email', 'like', $like);
                });
            })
            ->when($studentType !== '', fn (Builder $query) => $query->where('students.student_type', $studentType))
            ->when($courseId !== null, fn (Builder $query) => $query->where('students.course_id', $courseId))
            ->when($yearLevel !== null, fn (Builder $query) => $query->where('students.academic_year', $yearLevel));

        // Compute compliance KPIs & per-column readiness in SQL without hydrating the full roster
        $cohortGroups = $this->documentService->resolveCohortGroups($scopeQuery);
        $stats = $this->documentService->cohortComplianceStats($scopeQuery, $cohortGroups, $matrixColumns);
        $kpis = $stats['kpis'];
        $columnStats = $stats['column_stats'];

        // Apply compliance & missing_doc filters in SQL, paginate first, and hydrate display relations only for the page
        $filteredQuery = (clone $scopeQuery)
            ->with(array_filter([
                'DocumentLocation',
                'Course:id,code,title',
                'shsStrand:id,strand_name',
                'user:id,email',
                $hasDocRecordsTable ? 'documentRecords.verifier:id,name' : null,
            ]))
            ->withCount('resources');

        $this->documentService->applyComplianceFilters($filteredQuery, $cohortGroups, $compliance, $missingDoc);

        if ($sort === 'name') {
            $filteredQuery->orderBy('students.last_name', $direction)->orderBy('students.first_name', $direction);
        } else {
            $filteredQuery->orderBy("students.{$sort}", $direction);
        }

        $paginated = $filteredQuery->paginate($perPage)->withQueryString();

        $paginated->through(function (Student $student) use ($hasDocRecordsTable): array {
            $summary = $this->documentService->rowSummaryFor(
                $student,
                $hasDocRecordsTable ? $student->documentRecords : collect(),
            );

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'lrn' => $student->lrn,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'student_type' => $student->student_type?->value,
                'academic_year' => $student->formatted_academic_year,
                'course_code' => $student->Course?->code ?? $student->shsStrand?->strand_name,
                'course_title' => $student->Course?->title,
                'avatar_url' => $student->picture1x1 !== '' ? $student->picture1x1 : null,
                'document_location' => $student->DocumentLocation,
                'dynamic_count' => (int) ($student->resources_count ?? 0),
                'summary' => [
                    'required_total' => $summary['required_total'],
                    'compliant_count' => $summary['compliant_count'],
                    'uploaded_count' => $summary['uploaded_count'],
                    'pending_review_count' => $summary['pending_review_count'],
                    'rejected_count' => $summary['rejected_count'],
                    'missing_count' => $summary['missing_count'],
                    'compliance_percent' => $summary['compliance_percent'],
                    'is_complete' => $summary['is_complete'],
                ],
                'slots' => $summary['slots'],
            ];
        });

        $config = $this->configuration->get($schoolId);
        $schoolProfile = $this->configuration->schoolProfile($schoolId);

        return Inertia::render('administrators/students/documents/list', [
            'students' => $paginated,
            'matrix_columns' => $matrixColumns,
            'column_stats' => array_values($columnStats),
            'kpis' => $kpis,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'compliance' => $compliance,
                'missing_doc' => $missingDoc !== '' ? $missingDoc : null,
                'student_type' => $studentType !== '' ? $studentType : null,
                'course_id' => $courseId,
                'year_level' => $yearLevel,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'view' => $viewMode,
            ],
            'options' => [
                'student_configuration' => $config,
                'identifier_policy' => app(IdentifierGenerator::class)->studentPolicy(schoolId: $schoolId),
                'school_profile' => $schoolProfile,
                'types' => collect(StudentType::cases())
                    ->filter(fn (StudentType $type): bool => in_array($type->value, $config['enabled_types'], true))
                    ->map(fn (StudentType $type): array => [
                        'value' => $type->value,
                        'label' => $config['type_labels'][$type->value] ?? $type->getLabel(),
                    ])
                    ->values()
                    ->all(),
                'courses' => Course::query()
                    ->orderBy('code')
                    ->get(['id', 'code', 'title'])
                    ->map(fn (Course $course): array => [
                        'value' => (string) $course->id,
                        'label' => "{$course->code} - {$course->title}",
                    ])
                    ->values()
                    ->all(),
                'year_levels' => $this->configuration->filterYearLevels($schoolId, $studentType !== '' ? $studentType : null),
            ],
        ]);
    }

    public function index(Student $student): Response
    {
        $this->authorizeDocumentAccess(['ViewAny:Student', 'View:Student'], $student);

        $student->load(['DocumentLocation', 'Course', 'shsStrand', 'resources' => function ($query): void {
            $query->orderBy('created_at', 'desc');
        }]);

        $dossier = $this->documentService->dossierFor($student);

        return Inertia::render('administrators/students/documents/index', [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'lrn' => $student->lrn,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'profile_url' => $student->picture1x1 !== '' ? $student->picture1x1 : $student->profile_url,
                'course' => $student->Course
                    ? ['code' => $student->Course->code, 'title' => $student->Course->title]
                    : ($student->shsStrand ? ['code' => $student->shsStrand->strand_name, 'title' => 'Senior High Strand'] : null),
                'academic_year' => $student->formatted_academic_year,
                'student_type' => $student->student_type?->value,
            ],
            'fixed_documents' => $student->DocumentLocation?->toResolvedDocumentArray($student) ?? (new DocumentLocation)->toResolvedDocumentArray($student),
            'dynamic_documents' => $dossier['dynamic_documents'],
            'dossier' => $dossier,
            'school_profile' => $this->configuration->schoolProfile($student->school_id),
            'identifier_policy' => app(IdentifierGenerator::class)->studentPolicy(schoolId: $student->school_id),
            'export_zip_url' => route('administrators.students.documents.export-zip', $student->id),
        ]);
    }

    public function dossierJson(Student $student): JsonResponse
    {
        $this->authorizeDocumentAccess(['ViewAny:Student', 'View:Student'], $student);

        $dossier = $this->documentService->dossierFor($student);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'student_type' => $student->student_type?->value,
                'academic_year' => $student->formatted_academic_year,
            ],
            'dossier' => $dossier,
            'export_zip_url' => route('administrators.students.documents.export-zip', $student->id),
        ]);
    }

    public function updateFixed(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        $this->authorizeDocumentAccess('Update:Student', $student);

        $request->validate([
            'document_type' => ['required', 'string', Rule::in(DocumentLocation::DOCUMENT_FIELDS)],
            'auto_verify' => ['nullable', 'boolean'],
            'file' => [
                'required',
                'file',
                'max:5120',
                $request->input('document_type') === 'picture_1x1'
                    ? 'mimes:jpeg,jpg,png,webp'
                    : 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,webp,zip,rar',
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        /** @var User|null $actor */
        $actor = Auth::user();

        $this->documentService->uploadFixed(
            student: $student,
            documentKey: (string) $request->input('document_type'),
            file: $file,
            actor: $actor,
            autoVerify: $request->boolean('auto_verify', true),
        );

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Document uploaded successfully.',
                'dossier' => $this->documentService->dossierFor($student->fresh()),
            ]);
        }

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function destroyFixed(Request $request, Student $student, ?string $documentType = null): RedirectResponse|JsonResponse
    {
        $this->authorizeDocumentAccess('Update:Student', $student);

        $type = $documentType ?: $request->input('document_type');

        if (! is_string($type) || ! in_array($type, DocumentLocation::DOCUMENT_FIELDS, true)) {
            abort(404, 'Invalid document type.');
        }

        /** @var User|null $actor */
        $actor = Auth::user();
        $this->documentService->removeFixed($student, $type, $actor);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Document removed successfully.',
                'dossier' => $this->documentService->dossierFor($student->fresh()),
            ]);
        }

        return redirect()->back()->with('success', 'Document removed successfully.');
    }

    public function reviewFixed(Request $request, Student $student, string $documentType): RedirectResponse|JsonResponse
    {
        $this->authorizeDocumentAccess('Update:Student', $student);

        if (! in_array($documentType, DocumentLocation::DOCUMENT_FIELDS, true)) {
            abort(404, 'Invalid document type.');
        }

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['verify', 'reject', 'physical', 'waive', 'reset'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date'],
            'notify_student' => ['nullable', 'boolean'],
        ]);

        /** @var User|null $actor */
        $actor = Auth::user();

        $notified = false;
        $record = $this->documentService->review(
            student: $student,
            documentKey: $documentType,
            action: (string) $validated['action'],
            actor: $actor,
            reason: $validated['reason'] ?? null,
            notes: $validated['notes'] ?? null,
            expiresAt: array_key_exists('expires_at', $validated) ? ($validated['expires_at'] ?? '') : null,
            notifyStudent: (bool) ($validated['notify_student'] ?? true),
            notified: $notified,
        );

        $message = match ($validated['action']) {
            'verify' => 'Document marked as verified.',
            'reject' => $notified ? 'Document rejected and student notified.' : 'Document rejected.',
            'physical' => 'Marked physical copy on file.',
            'waive' => 'Document requirement waived.',
            default => 'Document status reset.',
        };

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => $message,
                'record' => $record,
                'dossier' => $this->documentService->dossierFor($student->fresh()),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function bulkRemind(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeDocumentAccess('Update:Student');

        $validated = $request->validate([
            'student_ids' => ['nullable', 'array', 'max:500'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'document_key' => ['nullable', 'string', Rule::in(DocumentLocation::DOCUMENT_FIELDS)],
            'scope' => ['nullable', 'string', Rule::in(['selected', 'cohort'])],
            'search' => ['nullable', 'string', 'max:255'],
            'student_type' => ['nullable', 'string', 'max:64'],
            'course_id' => ['nullable', 'integer'],
            'year_level' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $hasStudentIdsParam = $request->exists('student_ids');
        $studentIds = is_array($validated['student_ids'] ?? null)
            ? array_values(array_unique(array_map('intval', $validated['student_ids'])))
            : [];
        $specificKeys = filled($validated['document_key'] ?? null) ? [(string) $validated['document_key']] : null;
        $isExplicitCohort = ($validated['scope'] ?? null) === 'cohort'
            || (! $hasStudentIdsParam && $specificKeys !== null);

        if (($hasStudentIdsParam && $studentIds === []) || ($studentIds === [] && ! $isExplicitCohort)) {
            $feedback = 'No students were selected for reminders.';

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $feedback, 'sent_count' => 0]);
            }

            return redirect()->back()->with('info', $feedback);
        }

        $query = Student::query()->with(['DocumentLocation', 'user']);
        if ($studentIds !== []) {
            $query->whereIn('id', $studentIds);
        } else {
            $search = is_string($validated['search'] ?? null) ? mb_trim($validated['search']) : '';
            $studentType = is_string($validated['student_type'] ?? null) ? mb_trim($validated['student_type']) : '';
            $courseId = isset($validated['course_id']) && (int) $validated['course_id'] > 0 ? (int) $validated['course_id'] : null;
            $yearLevel = isset($validated['year_level']) && (int) $validated['year_level'] > 0 ? (int) $validated['year_level'] : null;

            $query
                ->when($search !== '', function (Builder $q) use ($search): void {
                    $like = "%{$search}%";
                    $q->where(function (Builder $sub) use ($like): void {
                        $sub->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('student_id', 'like', $like)
                            ->orWhere('lrn', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                })
                ->when($studentType !== '', fn (Builder $q) => $q->where('student_type', $studentType))
                ->when($courseId !== null, fn (Builder $q) => $q->where('course_id', $courseId))
                ->when($yearLevel !== null, fn (Builder $q) => $q->where('academic_year', $yearLevel));
        }
        if (Schema::hasTable('student_document_records')) {
            $query->with('documentRecords');
        }

        $students = $query->limit(500)->get();
        $sent = 0;

        foreach ($students as $student) {
            if ($this->documentService->remindMissing($student, $specificKeys, $validated['message'] ?? null)) {
                $sent++;
            }
        }

        $feedback = $sent > 0
            ? "Sent missing-document reminders to {$sent} student(s)."
            : 'No students required reminders or had reachable contact details.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $feedback, 'sent_count' => $sent]);
        }

        return redirect()->back()->with($sent > 0 ? 'success' : 'info', $feedback);
    }

    public function storeDynamic(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        $this->authorizeDocumentAccess('Update:Student', $student);

        $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,webp,zip,rar',
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $disk = $this->documentService->privateDisk();

        $path = $file->store("students/{$student->id}/resources", $disk);

        $student->resources()->create([
            'type' => $request->input('type'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'disk' => $disk,
            'file_size' => $file->getSize(),
        ]);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Document uploaded successfully.',
                'dossier' => $this->documentService->dossierFor($student->fresh()),
            ]);
        }

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function destroyDynamic(Request $request, Student $student, Resource $resource): RedirectResponse|JsonResponse
    {
        $this->authorizeDocumentAccess('Update:Student', $student);

        if ($resource->resourceable_type !== Student::class || (int) $resource->resourceable_id !== (int) $student->id) {
            abort(403);
        }

        $filePath = $resource->file_path;
        $recordedDisk = $resource->disk;

        $resource->delete();

        if (is_string($filePath) && mb_trim($filePath) !== '' && ! str_contains($filePath, '..')) {
            $candidateDisks = array_values(array_unique(array_filter([
                $recordedDisk,
                $this->documentService->privateDisk(),
                'private',
                'public',
            ])));

            foreach ($candidateDisks as $disk) {
                if (Storage::disk($disk)->exists($filePath)) {
                    Storage::disk($disk)->delete($filePath);

                    break;
                }
            }
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Document deleted successfully.',
                'dossier' => $this->documentService->dossierFor($student->fresh()),
            ]);
        }

        return redirect()->back()->with('success', 'Document deleted successfully.');
    }

    public function showFixed(Request $request, Student $student, string $documentType): StreamedResponse
    {
        $this->authorizeDocumentAccess(['ViewAny:Student', 'View:Student'], $student);

        $resolved = $this->documentService->resolveFixedFile($student, $documentType);
        if ($resolved === null) {
            abort(404, 'Document not found.');
        }

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if ($request->boolean('preview')) {
            return Storage::disk($resolved['disk'])->response($resolved['path'], $resolved['filename'], $headers);
        }

        return Storage::disk($resolved['disk'])->download($resolved['path'], $resolved['filename'], $headers);
    }

    public function showDynamic(Request $request, Student $student, Resource $resource): StreamedResponse
    {
        $this->authorizeDocumentAccess(['ViewAny:Student', 'View:Student'], $student);

        if ($resource->resourceable_type !== Student::class || (int) $resource->resourceable_id !== (int) $student->id) {
            abort(403);
        }

        $resolved = $this->documentService->resolveDynamicFile($student, $resource);
        if ($resolved === null) {
            abort(404, 'Document file not found on storage.');
        }

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if ($request->boolean('preview')) {
            return Storage::disk($resolved['disk'])->response($resolved['path'], $resolved['filename'], $headers);
        }

        return Storage::disk($resolved['disk'])->download($resolved['path'], $resolved['filename'], $headers);
    }

    public function exportZip(Student $student): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeDocumentAccess(['ViewAny:Student', 'View:Student'], $student);

        $zipPath = $this->documentService->createDossierZip($student);
        if ($zipPath === null) {
            return redirect()->back()->with('info', 'No uploaded document files are available to export for this student.');
        }

        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($student->student_id ?? $student->id)) ?: (string) $student->id;

        return response()
            ->download($zipPath, "student_{$safeId}_documents.zip")
            ->deleteFileAfterSend(true);
    }

    /**
     * @param  string|array<int, string>  $permissions
     */
    private function authorizeDocumentAccess(string|array $permissions, ?Student $student = null): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        $this->abortUnlessUserHasAnyPermission(
            $user,
            $permissions,
            [UserRole::Admin, UserRole::Registrar, UserRole::AssistantRegistrar],
        );

        if ($student instanceof Student && $student->school_id !== null) {
            $tenantContext = app(TenantContext::class);
            $currentSchoolId = $tenantContext->getCurrentSchoolId();
            if ($currentSchoolId !== null && (int) $student->school_id !== (int) $currentSchoolId && ! $tenantContext->canAccessOrganization((int) $student->school_id)) {
                abort(403, 'You do not have access to this student record.');
            }
        }
    }
}
