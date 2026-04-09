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

final class AdministratorStudentDocumentController extends Controller
{
    public function listAll(Request $request)
    {
        $search = $request->input('search');

        $students = Student::query()
            ->with('DocumentLocation')
            ->when($search, function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('student_id', 'ilike', "%{$search}%");
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
            'fixed_documents' => $student->DocumentLocation ?: new DocumentLocation(),
            'dynamic_documents' => $student->resources,
        ]);
    }

    public function updateFixed(Request $request, Student $student)
    {
        $request->validate([
            'document_type' => ['required', 'string', 'in:birth_certificate,form_138,form_137,good_moral_cert,transfer_credentials,transcript_records,picture_1x1'],
            'file' => ['required', 'file', 'max:5120'], // 5MB max
        ]);

        $type = $request->input('document_type');
        $file = $request->file('file');

        $path = $file->store("students/{$student->id}/documents", 'public');

        if (! $student->DocumentLocation) {
            $docLocation = DocumentLocation::create([$type => $path]);
            $student->document_location_id = $docLocation->id;
            $student->save();
        } else {
            // Delete old file if exists
            $oldPath = $student->DocumentLocation->$type;
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $student->DocumentLocation->update([$type => $path]);
        }

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function storeDynamic(Request $request, Student $student)
    {
        $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'file' => ['required', 'file', 'max:5120'], // 5MB max
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $path = $file->store("students/{$student->id}/resources", 'public');

        $student->resources()->create([
            'type' => $request->input('type'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'disk' => 'public',
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

        if (Storage::disk($resource->disk)->exists($resource->file_path)) {
            Storage::disk($resource->disk)->delete($resource->file_path);
        }

        $resource->delete();

        return redirect()->back()->with('success', 'Document deleted successfully.');
    }
}
