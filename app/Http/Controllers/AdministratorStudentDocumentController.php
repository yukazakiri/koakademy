<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentLocation;
use App\Models\Resource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdministratorStudentDocumentController extends Controller
{
    public function listAll(Request $request)
    {
        $search = $request->input('search');

        $students = Student::query()
            ->with('DocumentLocation')
            ->when($search, function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->whereLike('first_name', "%{$search}%")
                        ->orWhereLike('last_name', "%{$search}%")
                        ->orWhereLike('student_id', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('administrators/students/documents/list', [
            'students' => $students,
            'filters' => $request->only(['search']),
        ]);
    }

    public function index(Student $student)
    {
        $student->load(['DocumentLocation', 'resources' => function ($query): void {
            $query->orderBy('created_at', 'desc');
        }]);

        return Inertia::render('administrators/students/documents/index', [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'full_name' => $student->full_name,
                'profile_url' => $student->profile_url,
                'course' => $student->course ? ['code' => $student->course->code, 'title' => $student->course->title] : null,
                'student_type' => $student->student_type,
            ],
            'fixed_documents' => $student->DocumentLocation?->toResolvedDocumentArray($student) ?? new DocumentLocation()->toResolvedDocumentArray($student),
            'dynamic_documents' => $student->resources->map(function (Resource $resource) use ($student): array {
                $arr = $resource->toArray();
                $arr['url'] = route('administrators.students.documents.dynamic.show', [
                    'student' => $student->id,
                    'resource' => $resource->id,
                ]);

                return $arr;
            }),
        ]);
    }

    public function updateFixed(Request $request, Student $student)
    {
        $request->validate([
            'document_type' => ['required', 'string', 'in:birth_certificate,form_138,form_137,good_moral_cert,transfer_credentials,transcript_records,picture_1x1'],
            'file' => [
                'required',
                'file',
                'max:5120', // 5MB max
                $request->input('document_type') === 'picture_1x1'
                    ? 'mimes:jpeg,jpg,png,webp'
                    : 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,zip,rar',
            ],
        ]);

        $type = $request->input('document_type');
        $file = $request->file('file');

        $path = $file->store("students/{$student->id}/documents", 'private');

        if (! $student->DocumentLocation) {
            $docLocation = DocumentLocation::create([$type => $path]);
            $student->document_location_id = $docLocation->id;
            $student->save();
        } else {
            // Delete old file if exists
            $oldPath = $student->DocumentLocation->$type;
            if (is_string($oldPath) && $oldPath !== '' && ! filter_var($oldPath, FILTER_VALIDATE_URL) && ! str_starts_with($oldPath, '/')) {
                foreach (['private', config('filesystems.default'), 'public'] as $disk) {
                    if (Storage::disk($disk)->exists($oldPath)) {
                        Storage::disk($disk)->delete($oldPath);
                    }
                }
            }

            $student->DocumentLocation->update([$type => $path]);
        }

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function destroyFixed(Request $request, Student $student, ?string $documentType = null)
    {
        $type = $documentType ?: $request->input('document_type');

        if (! is_string($type) || ! in_array($type, DocumentLocation::DOCUMENT_FIELDS, true)) {
            abort(404, 'Invalid document type.');
        }

        if ($student->DocumentLocation) {
            $oldPath = $student->DocumentLocation->$type;
            if (is_string($oldPath) && $oldPath !== '' && ! filter_var($oldPath, FILTER_VALIDATE_URL) && ! str_starts_with($oldPath, '/')) {
                foreach (['private', config('filesystems.default'), 'public'] as $disk) {
                    if (Storage::disk($disk)->exists($oldPath)) {
                        Storage::disk($disk)->delete($oldPath);
                    }
                }
            }

            $student->DocumentLocation->update([$type => null]);
        }

        if ($type === 'picture_1x1' && $student->profile_url) {
            $profileUrl = $student->profile_url;
            if (is_string($profileUrl) && $profileUrl !== '' && ! filter_var($profileUrl, FILTER_VALIDATE_URL) && ! str_starts_with($profileUrl, '/')) {
                foreach (['private', config('filesystems.default'), 'public'] as $disk) {
                    if (Storage::disk($disk)->exists($profileUrl)) {
                        Storage::disk($disk)->delete($profileUrl);
                    }
                }
            }
            $student->update(['profile_url' => null]);
        }

        return redirect()->back()->with('success', 'Document removed successfully.');
    }

    public function storeDynamic(Request $request, Student $student)
    {
        $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'file' => [
                'required',
                'file',
                'max:5120', // 5MB max
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,zip,rar',
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $path = $file->store("students/{$student->id}/resources", 'private');

        $student->resources()->create([
            'type' => $request->input('type'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'disk' => 'private',
            'file_size' => $file->getSize(),
        ]);

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function destroyDynamic(Student $student, Resource $resource)
    {
        // Ensure the resource belongs to the student
        if ($resource->resourceable_type !== Student::class || $resource->resourceable_id !== $student->id) {
            abort(403);
        }

        if ($resource->disk && Storage::disk($resource->disk)->exists($resource->file_path)) {
            Storage::disk($resource->disk)->delete($resource->file_path);
        } elseif (Storage::disk('private')->exists($resource->file_path)) {
            Storage::disk('private')->delete($resource->file_path);
        } elseif (Storage::disk('public')->exists($resource->file_path)) {
            Storage::disk('public')->delete($resource->file_path);
        }

        $resource->delete();

        return redirect()->back()->with('success', 'Document deleted successfully.');
    }

    public function showFixed(Request $request, Student $student, string $documentType): StreamedResponse
    {
        if (! in_array($documentType, DocumentLocation::DOCUMENT_FIELDS, true)) {
            abort(404, 'Invalid document type.');
        }

        $path = $student->DocumentLocation?->$documentType;
        if (! is_string($path) || $path === '') {
            abort(404, 'Document not found.');
        }

        if (str_contains($path, '..')) {
            abort(400, 'Invalid path.');
        }

        $disk = 'private';
        if (! Storage::disk('private')->exists($path)) {
            if (Storage::disk('public')->exists($path)) {
                $disk = 'public';
            } elseif (Storage::disk(config('filesystems.default'))->exists($path)) {
                $disk = config('filesystems.default');
            } else {
                abort(404, 'Document file not found on storage.');
            }
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = "{$student->student_id}_{$documentType}".($extension !== '' ? ".{$extension}" : '');

        return Storage::disk($disk)->download($path, $filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function showDynamic(Request $request, Student $student, Resource $resource): StreamedResponse
    {
        if ($resource->resourceable_type !== Student::class || (int) $resource->resourceable_id !== (int) $student->id) {
            abort(403);
        }

        $path = $resource->file_path;
        if (! is_string($path) || $path === '' || str_contains($path, '..')) {
            abort(404, 'Document not found.');
        }

        $disk = $resource->disk && Storage::disk($resource->disk)->exists($path)
            ? $resource->disk
            : (Storage::disk('private')->exists($path)
                ? 'private'
                : (Storage::disk('public')->exists($path)
                    ? 'public'
                    : null));

        if (! $disk) {
            abort(404, 'Document file not found on storage.');
        }

        return Storage::disk($disk)->download($path, $resource->file_name ?: basename($path), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
