<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Features\OnlineCollegeEnrollment;
use App\Features\OnlineTesdaEnrollment;
use App\Http\Requests\StoreEnrollmentRegistrationRequest;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

final class EnrollmentRegistrationController extends Controller
{
    public function create(): Response
    {
        $collegeEnabled = Feature::active(OnlineCollegeEnrollment::class);
        $tesdaEnabled = Feature::active(OnlineTesdaEnrollment::class);

        if (! $collegeEnabled && ! $tesdaEnabled) {
            return Inertia::render('enrollment/closed', [
                'message' => 'Online enrollment is currently unavailable. Please check back later or visit the registrar\'s office.',
            ]);
        }

        $courses = Course::query()
            ->where('is_active', true)
            ->select(['id', 'code', 'title', 'department', 'description'])
            ->orderBy('department')
            ->orderBy('title')
            ->get();

        $departments = [
            ['code' => 'IT', 'label' => 'Information Technology'],
            ['code' => 'HM', 'label' => 'Hospitality Management'],
            ['code' => 'BA', 'label' => 'Business Administration'],
            ['code' => 'HRM', 'label' => 'Human Resource Management'],
            ['code' => 'TESDA', 'label' => 'TESDA'],
        ];

        return Inertia::render('enrollment/index', [
            'departments' => $departments,
            'courses' => $courses,
            'flash' => session('flash'),
            'college_enrollment_enabled' => $collegeEnabled,
            'tesda_enrollment_enabled' => $tesdaEnabled,
        ]);
    }

    public function store(StoreEnrollmentRegistrationRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $studentTypeValue = $payload['student_type'] ?? '';

        // Check feature flags before allowing submission
        if ($studentTypeValue === 'college' && ! Feature::active(OnlineCollegeEnrollment::class)) {
            return redirect()->back()->with('flash', [
                'error' => 'College online registration is currently unavailable.',
            ]);
        }

        if ($studentTypeValue === 'tesda' && ! Feature::active(OnlineTesdaEnrollment::class)) {
            return redirect()->back()->with('flash', [
                'error' => 'TESDA online registration is currently unavailable.',
            ]);
        }

        $studentType = match ($payload['student_type']) {
            'tesda' => StudentType::TESDA,
            default => StudentType::College,
        };

        $courseId = (int) $payload['course_id'];
        $course = Course::query()->findOrFail($courseId);

        if ($studentType === StudentType::TESDA && mb_strtoupper(mb_trim((string) $course->department)) !== 'TESDA') {
            return redirect()->back()->with('flash', [
                'error' => 'TESDA applicants must select a TESDA course/program.',
            ]);
        }

        $birthDate = Carbon::parse($payload['birth_date']);
        $academicYear = isset($payload['academic_year']) && $payload['academic_year'] !== '' ? (int) $payload['academic_year'] : null;

        /** @var Student $student */
        $student = DB::transaction(function () use ($request, $payload, $studentType, $birthDate, $courseId, $academicYear): Student {
            $studentId = Student::generateNextId($studentType);

            $studentContactId = null;
            $studentContactAttributes = $this->onlyExistingColumns('student_contacts', [
                'personal_contact' => $payload['contacts']['personal_contact'] ?? null,
                'emergency_contact_name' => $payload['contacts']['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $payload['contacts']['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $payload['contacts']['emergency_contact_relationship'] ?? null,
            ]);

            if ($studentContactAttributes !== []) {
                $studentContactId = (int) DB::table('student_contacts')->insertGetId($studentContactAttributes);
            }

            $studentParentInfoId = null;
            $studentParentInfoAttributes = $this->onlyExistingColumns('student_parents_info', [
                'father_name' => $payload['parents']['father_name'] ?? null,
                'father_contact' => $payload['parents']['father_contact'] ?? null,
                'mother_name' => $payload['parents']['mother_name'] ?? null,
                'mother_contact' => $payload['parents']['mother_contact'] ?? null,
                'guardian_name' => $payload['parents']['guardian_name'] ?? null,
                'guardian_relationship' => $payload['parents']['guardian_relationship'] ?? null,
                'guardian_contact' => $payload['parents']['guardian_contact'] ?? null,
                'family_address' => $payload['parents']['family_address'] ?? null,
            ]);

            if ($studentParentInfoAttributes !== []) {
                $studentParentInfoId = (int) DB::table('student_parents_info')->insertGetId($studentParentInfoAttributes);
            }

            $studentEducationInfoId = null;
            $studentEducationInfoAttributes = $this->onlyExistingColumns('student_education_info', [
                'elementary_school' => $payload['education']['elementary_school'] ?? null,
                'elementary_year_graduated' => $payload['education']['elementary_year_graduated'] ?? null,
                'high_school' => $payload['education']['high_school'] ?? null,
                'high_school_year_graduated' => $payload['education']['high_school_year_graduated'] ?? null,
                'senior_high_school' => $payload['education']['senior_high_school'] ?? null,
                'senior_high_year_graduated' => $payload['education']['senior_high_year_graduated'] ?? null,
                'vocational_school' => $payload['education']['vocational_school'] ?? null,
                'vocational_course' => $payload['education']['vocational_course'] ?? null,
                'vocational_year_graduated' => $payload['education']['vocational_year_graduated'] ?? null,
            ]);

            if ($studentEducationInfoAttributes !== []) {
                $studentEducationInfoId = (int) DB::table('student_education_info')->insertGetId($studentEducationInfoAttributes);
            }

            // Process uploaded documents
            $uploadedDocuments = $this->processDocumentUploads($request, (string) $studentId);

            $contacts = array_filter([
                'personal_contact' => $payload['contacts']['personal_contact'] ?? null,
                'emergency_contact_name' => $payload['contacts']['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $payload['contacts']['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $payload['contacts']['emergency_contact_relationship'] ?? null,
                'parents' => $payload['parents'] ?? null,
                'education' => $payload['education'] ?? null,
                'consent' => true,
                'documents' => $uploadedDocuments !== [] ? $uploadedDocuments : null,
            ], static fn ($value): bool => $value !== null && $value !== '');

            return Student::query()->create([
                'student_id' => $studentId,
                'student_type' => $studentType,
                'lrn' => null,
                'first_name' => $payload['first_name'],
                'middle_name' => $payload['middle_name'] ?? null,
                'last_name' => $payload['last_name'],
                'suffix' => $payload['suffix'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'birth_date' => $birthDate,
                'age' => (int) $birthDate->diffInYears(now()),
                'gender' => $payload['gender'],
                'civil_status' => $payload['civil_status'] ?? null,
                'nationality' => $payload['nationality'],
                'religion' => $payload['religion'] ?? null,
                'address' => $payload['address'],
                'emergency_contact' => $payload['contacts']['emergency_contact_phone'] ?? null,
                'course_id' => $courseId,
                'academic_year' => $academicYear,
                'student_contact_id' => $studentContactId,
                'student_parent_info' => $studentParentInfoId,
                'student_education_id' => $studentEducationInfoId,
                'status' => StudentStatus::Applicant,
                'contacts' => $contacts,
                'scholarship_type' => null, // Explicitly not a scholar yet
            ]);
        });

        return redirect()->route('enrollment.create')->with('flash', [
            'success' => sprintf(
                'Registration submitted for %s %s (Applicant ID: %s).',
                $student->first_name,
                $student->last_name,
                (string) $student->student_id
            ),
            'studentId' => (string) $student->student_id,
            'studentName' => sprintf('%s %s', $student->first_name, $student->last_name),
            'course' => $course->title,
        ]);
    }

    /**
     * Process and store uploaded documents.
     *
     * @return array<int, array{type: string, path: string, original_name: string, size: int, mime_type: string, uploaded_at: string}>
     */
    private function processDocumentUploads(StoreEnrollmentRegistrationRequest $request, string $studentId): array
    {
        $uploadedDocuments = [];

        if (! $request->hasFile('documents')) {
            return $uploadedDocuments;
        }

        $documents = $request->file('documents');

        if (! is_array($documents)) {
            return $uploadedDocuments;
        }

        $disk = Storage::disk('private');
        $basePath = sprintf('enrollment-documents/%s', $studentId);

        foreach ($documents as $index => $documentData) {
            if (! is_array($documentData)) {
                continue;
            }
            if (! isset($documentData['file'])) {
                continue;
            }
            /** @var UploadedFile|null $file */
            $file = $documentData['file'];
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if (! $file->isValid()) {
                continue;
            }

            $type = is_string($documentData['type'] ?? null) ? $documentData['type'] : 'unknown';
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            // Generate a unique filename: type_timestamp_hash.ext
            $filename = sprintf(
                '%s_%s_%s.%s',
                preg_replace('/[^a-zA-Z0-9-]/', '_', mb_strtolower($type)),
                now()->format('Ymd_His'),
                mb_substr(md5($index.$originalName), 0, 8),
                $extension
            );

            $path = $disk->putFileAs($basePath, $file, $filename);

            if ($path === false) {
                continue;
            }

            $uploadedDocuments[] = [
                'type' => $type,
                'path' => $path,
                'original_name' => $originalName,
                'size' => $file->getSize() ?: 0,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'uploaded_at' => format_timestamp_now(),
            ];
        }

        return $uploadedDocuments;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function onlyExistingColumns(string $table, array $attributes): array
    {
        $filtered = [];

        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($value === '') {
                continue;
            }
            if (! Schema::hasColumn($table, (string) $key)) {
                continue;
            }

            $filtered[(string) $key] = $value;
        }

        return $filtered;
    }
}
