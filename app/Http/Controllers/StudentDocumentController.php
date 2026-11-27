<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentLocation;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentConfigurationService;
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
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $student = $this->resolveStudent($user);
        $docLocation = $student?->DocumentLocation;
        $checklist = app(StudentConfigurationService::class)->documentChecklist($student?->school_id);

        $documents = [];
        $uploadedCount = 0;

        foreach ($checklist as $key => $meta) {
            $storedPath = $docLocation?->getAttribute($key);
            $hasFile = is_string($storedPath) && mb_trim($storedPath) !== '';

            if ($hasFile) {
                $uploadedCount++;
            }

            $documents[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'accepted' => $meta['accepted'],
                'required' => $meta['required'],
                'status' => $hasFile ? 'on_file' : 'missing',
                'file_name' => $hasFile ? basename($storedPath) : null,
                'download_url' => $hasFile ? route('student.documents.download', ['documentType' => $key]) : null,
                'preview_url' => $hasFile ? route('student.documents.download', ['documentType' => $key, 'preview' => 1]) : null,
            ];
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
                'course' => $student->Course?->title ?? $student->Course?->name ?? $student->shsStrand?->name,
            ] : null,
            'documents' => $documents,
            'summary' => [
                'uploaded' => $uploadedCount,
                'total' => count($checklist),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $student = $this->resolveStudent($user);

        abort_if(! $student instanceof Student, 404, 'Student record not found.');

        $checklist = app(StudentConfigurationService::class)->documentChecklist($student->school_id);
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
        $privateDisk = (string) config('filesystems.private_disk', 'private');

        $path = $file->store("students/{$student->id}/documents", $privateDisk);
        abort_if(! is_string($path) || $path === '', 500, 'Could not store document.');

        if (! $student->DocumentLocation) {
            $docLocation = DocumentLocation::create([$type => $path]);
            $student->document_location_id = $docLocation->id;
            $student->save();
        } else {
            $oldPath = $student->DocumentLocation->getAttribute($type);
            if (is_string($oldPath) && $oldPath !== '' && ! filter_var($oldPath, FILTER_VALIDATE_URL) && ! str_starts_with($oldPath, '/')) {
                $candidateDisks = array_values(array_unique(array_filter([
                    $privateDisk,
                    'private',
                    is_string(config('filesystems.default')) ? (string) config('filesystems.default') : null,
                    'public',
                ])));

                foreach ($candidateDisks as $disk) {
                    if (Storage::disk($disk)->exists($oldPath)) {
                        Storage::disk($disk)->delete($oldPath);
                    }
                }
            }

            $student->DocumentLocation->update([$type => $path]);
        }

        $label = $checklist[$type]['label'] ?? str_replace('_', ' ', ucfirst($type));

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

        $configuredSlots = array_keys(app(StudentConfigurationService::class)->documentChecklist($student->school_id, onlyVisible: false));
        $allowedDocumentTypes = array_values(array_unique([...$configuredSlots, ...DocumentLocation::DOCUMENT_FIELDS]));

        if (! in_array($documentType, $allowedDocumentTypes, true) || ! in_array($documentType, DocumentLocation::DOCUMENT_FIELDS, true)) {
            abort(404, 'Invalid document type.');
        }

        $path = $student->DocumentLocation?->getAttribute($documentType);

        if (! is_string($path) || mb_trim($path) === '' || str_contains($path, '..')) {
            abort(404, 'Document not found.');
        }

        $privateDisk = (string) config('filesystems.private_disk', 'private');
        $candidateDisks = array_values(array_unique(array_filter([
            $privateDisk,
            'private',
            is_string(config('filesystems.default')) ? (string) config('filesystems.default') : null,
            'public',
        ])));

        $resolvedDisk = null;
        foreach ($candidateDisks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                $resolvedDisk = $disk;
                break;
            }
        }

        abort_if($resolvedDisk === null, 404, 'Document file not found on storage.');

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $studentNumber = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($student->student_id ?? $student->id)) ?: (string) $student->id;
        $filename = "{$studentNumber}_{$documentType}".($extension !== '' ? ".{$extension}" : '');

        if ($request->boolean('preview')) {
            return Storage::disk($resolvedDisk)->response($path, $filename, [
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return Storage::disk($resolvedDisk)->download($path, $filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
