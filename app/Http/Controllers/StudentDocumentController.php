<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentLocation;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentConfigurationService;
use App\Services\StudentDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StudentDocumentController extends Controller
{
    public function __construct(
        private readonly StudentDocumentService $documentService,
        private readonly StudentConfigurationService $configuration,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $student = $this->resolveStudent($user);

        $documents = [];
        $summary = [
            'uploaded' => 0,
            'compliant' => 0,
            'total' => 0,
            'pending_review' => 0,
            'rejected' => 0,
        ];

        if ($student instanceof Student) {
            $dossier = $this->documentService->dossierFor($student, forStudentPortal: true);
            $documents = collect($dossier['slots'])
                ->map(fn (array $slot): array => [
                    'key' => $slot['key'],
                    'label' => $slot['label'],
                    'description' => $slot['description'],
                    'accepted' => $slot['accepted'],
                    'required' => $slot['required'],
                    'status' => $slot['has_file'] && $slot['status'] === 'pending_review' ? 'pending_review' : $slot['status'],
                    'is_compliant' => $slot['is_compliant'],
                    'has_file' => $slot['has_file'],
                    'file_name' => $slot['file_name'],
                    'rejection_reason' => $slot['rejection_reason'],
                    'waiver_reason' => $slot['waiver_reason'],
                    'verified_at_label' => $slot['verified_at_label'],
                    'download_url' => $slot['download_url'],
                    'preview_url' => $slot['preview_url'],
                ])
                ->values()
                ->all();

            $docsCollection = collect($documents);
            $summary = [
                'uploaded' => $docsCollection->where('has_file', true)->count(),
                'compliant' => $docsCollection->where('is_compliant', true)->count(),
                'total' => $docsCollection->count(),
                'pending_review' => $docsCollection->where('status', 'pending_review')->count(),
                'rejected' => $docsCollection->where('status', 'rejected')->count(),
            ];
        } else {
            $checklist = $this->configuration->documentChecklist();
            foreach ($checklist as $key => $meta) {
                $documents[] = [
                    'key' => $key,
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'accepted' => $meta['accepted'],
                    'required' => $meta['required'],
                    'status' => 'missing',
                    'is_compliant' => false,
                    'has_file' => false,
                    'file_name' => null,
                    'rejection_reason' => null,
                    'waiver_reason' => null,
                    'verified_at_label' => null,
                    'download_url' => null,
                    'preview_url' => null,
                ];
            }
            $summary['total'] = count($documents);
        }

        return Inertia::render('student/documents/index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->value ?? 'student',
            ],
            'student' => $student ? [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'course' => $student->Course?->title ?? $student->Course?->name ?? $student->shsStrand?->strand_name,
            ] : null,
            'documents' => $documents,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $student = $this->resolveStudent($user);

        abort_if(! $student instanceof Student, 404, 'Student record not found.');

        $checklist = $this->configuration->documentChecklist($student->school_id);
        $allowedDocumentTypes = array_values(array_intersect(
            array_keys($checklist),
            DocumentLocation::DOCUMENT_FIELDS,
        ));

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in($allowedDocumentTypes)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $type = (string) $validated['document_type'];
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $this->documentService->uploadFixed(
            student: $student,
            documentKey: $type,
            file: $file,
            actor: $user,
            autoVerify: false,
        );

        $label = $checklist[$type]['label']
            ?? $this->documentService->labelFor($type, $student->student_type?->value, $student->school_id);

        return redirect()
            ->back()
            ->with('success', "{$label} uploaded successfully.");
    }

    public function download(Request $request, string $documentType): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $student = $this->resolveStudent($user);

        abort_if(! $student instanceof Student, 404, 'Student record not found.');

        $configuredSlots = array_keys($this->configuration->documentChecklist($student->school_id, onlyVisible: false));
        $allowedDocumentTypes = array_values(array_unique([...$configuredSlots, ...DocumentLocation::DOCUMENT_FIELDS]));

        if (! in_array($documentType, $allowedDocumentTypes, true) || ! in_array($documentType, DocumentLocation::DOCUMENT_FIELDS, true)) {
            abort(404, 'Invalid document type.');
        }

        $resolved = $this->documentService->resolveFixedFile($student, $documentType);
        abort_if($resolved === null, 404, 'Document not found.');

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if ($request->boolean('preview')) {
            return Storage::disk($resolved['disk'])->response($resolved['path'], $resolved['filename'], $headers);
        }

        return Storage::disk($resolved['disk'])->download($resolved['path'], $resolved['filename'], $headers);
    }

    private function resolveStudent(User $user): ?Student
    {
        return Student::query()
            ->with(['Course', 'shsStrand', 'DocumentLocation'])
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($unclaimed) use ($user): void {
                        $unclaimed->whereNull('user_id')
                            ->whereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim((string) $user->email))]);
                    });
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->first();
    }
}
