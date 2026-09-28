<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubjectEnrolledEnum;
use App\Models\Course;
use App\Models\CurriculumImport;
use App\Models\Department;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CurriculumImportService
{
    public function __construct(private TenantContext $tenants, private CurriculumWorkbookParser $parser) {}

    public function stage(User $actor, UploadedFile $file): CurriculumImport
    {
        $schoolId = $this->schoolId();
        $this->permit($actor, 'View:Course');

        $checksum = hash_file('sha256', $file->getRealPath());

        return CurriculumImport::query()->create([
            'public_id' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'uploaded_by_user_id' => $actor->id,
            'filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'checksum' => $checksum,
            'draft' => $this->parser->parse($file),
            'status' => 'review',
        ]);
    }

    public function find(string $id, User $actor): CurriculumImport
    {
        $this->permit($actor, 'View:Course');

        return CurriculumImport::query()->where('public_id', $id)
            ->where('school_id', $this->schoolId())
            ->where('uploaded_by_user_id', $actor->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function review(CurriculumImport $import, User $actor): array
    {
        $this->assertSchool($import);
        abort_unless($actor->id === $import->uploaded_by_user_id, 404);
        $this->permit($actor, 'View:Course');
        $draft = $import->draft;
        $candidates = Course::query()->where('school_id', $import->school_id)->get(['id', 'code', 'title']);
        $existingSubjects = Subject::query()->whereIn('code', array_column($draft['rows'], 'code'))->get(['code', 'course_id']);
        $programIds = $candidates->modelKeys();
        $normalize = static fn (string $text): string => mb_strtolower(mb_trim(preg_replace('/\s+/u', ' ', $text)));

        return [
            'id' => $import->public_id, 'filename' => $import->filename, 'status' => $import->status,
            'title' => $draft['title'], 'rows' => $draft['rows'], 'warnings' => $draft['warnings'],
            'existing_subjects' => $existingSubjects->map(fn (Subject $subject): array => [
                'code' => $subject->code,
                'course_id' => in_array($subject->course_id, $programIds, true) ? $subject->course_id : null,
            ])->all(),
            'candidates' => $candidates->map(fn (Course $c): array => [
                'id' => $c->id, 'code' => $c->code, 'title' => $c->title,
                'exact_title' => $normalize($c->title) === $normalize($draft['title']),
            ])->sortByDesc('exact_title')->values()->all(),
            'departments' => Department::query()->where('school_id', $import->school_id)->orderBy('name')->get(['id', 'name', 'code']),
            'course_types' => \App\Models\CourseType::query()->orderBy('name')->get(['id', 'name']),
            'course_id' => $import->course_id, 'selection' => $draft['selection'] ?? null,
        ];
    }

    /** @param array<string, mixed> $selection */
    public function approve(CurriculumImport $import, User $actor, array $selection): void
    {
        $this->assertSchool($import);
        if (! in_array($import->status, ['review', 'approved'], true) || $import->uploaded_by_user_id !== $actor->id) {
            throw ValidationException::withMessages(['import' => 'This draft is not available for confirmation.']);
        }
        $this->validateSelection($import, $actor, $selection);
        $import->update(['status' => 'approved', 'draft' => [...$import->draft, 'selection' => $selection]]);
    }

    /** @return array<string, mixed> */
    public function apply(CurriculumImport $import, User $actor): array
    {
        $this->assertSchool($import);
        $this->assertWritesEnabled();
        $this->permit($actor, 'View:Course');
        if ($import->uploaded_by_user_id !== $actor->id) {
            abort(404);
        }

        return DB::transaction(function () use ($import, $actor): array {
            $locked = CurriculumImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            $this->assertSchool($locked);
            $this->permit($actor, 'View:Course');
            if ($locked->status === 'completed') {
                return ['status' => 'completed', 'course_id' => $locked->course_id, 'already_applied' => true];
            }
            if ($locked->status !== 'approved' || $locked->uploaded_by_user_id !== $actor->id) {
                throw ValidationException::withMessages(['import' => 'Only an explicitly approved draft can be applied.']);
            }
            $selection = $locked->draft['selection'];
            $this->validateSelection($locked, $actor, $selection);

            if ($selection['mode'] === 'existing') {
                $course = Course::query()->where('school_id', $locked->school_id)->findOrFail($selection['course_id']);
            } else {
                $course = Course::query()->create([
                    'school_id' => $locked->school_id,
                    'code' => $selection['code'], 'title' => $selection['title'],
                    'department_id' => $selection['department_id'], 'course_type_id' => $selection['course_type_id'],
                    'curriculum_kind' => $selection['curriculum_kind'],
                    ...($selection['curriculum_kind'] === 'tesda_qualification' ? [
                        'tesda_program_type' => 'diploma', 'qualification_level' => 'PQF Level 5',
                        'duration_hours' => $selection['duration_hours'], 'duration_years' => $selection['duration_years'],
                        'internship_hours' => $selection['internship_hours'],
                        'bundled_qualifications' => $selection['bundled_qualifications'], 'advanced_topics' => $selection['advanced_topics'],
                    ] : []),
                    'units' => array_sum(array_map(static fn (array $row): int => ($row['skip'] ?? false) ? 0 : (int) $row['units'], $selection['rows'])),
                    'year_level' => max(array_column($locked->draft['rows'], 'year')),
                    'semester' => 1, 'is_active' => true,
                ]);
            }

            $created = 0;
            $updated = 0;
            $subjectIds = [];
            foreach ($locked->draft['rows'] as $index => $raw) {
                $row = $selection['rows'][$index];
                if ($row['skip'] ?? false) {
                    continue;
                }
                $code = mb_strtoupper(mb_trim($row['code']));
                $subject = Subject::query()->where('code', $code)->first();
                $attributes = [
                    'title' => mb_trim($row['title']), 'units' => (int) $row['units'],
                    'lecture' => (int) $row['lecture'], 'laboratory' => (int) $row['laboratory'],
                    'academic_year' => (int) $raw['year'], 'semester' => (int) $raw['semester'],
                ];
                if ($subject) {
                    if (array_diff_assoc($attributes, $subject->only(array_keys($attributes))) !== []) {
                        $subject->update($attributes);
                        $updated++;
                    }
                } else {
                    $subject = Subject::query()->create([
                        ...$attributes, 'code' => $code, 'course_id' => $course->id,
                        'classification' => SubjectEnrolledEnum::INTERNAL->value, 'is_credited' => true,
                    ]);
                    $created++;
                }
                $subjectIds[$code] = $subject->id;
            }

            foreach ($locked->draft['rows'] as $index => $raw) {
                $row = $selection['rows'][$index];
                if ($row['skip'] ?? false) {
                    continue;
                }
                $codes = $this->prerequisites($row['prerequisites'] ?? '');
                $subject = Subject::query()->findOrFail($subjectIds[mb_strtoupper(mb_trim($row['code']))]);
                $resolved = array_map(fn (string $code): int => $subjectIds[$code], $codes);
                if ($subject->pre_riquisite !== $resolved) {
                    $subject->update(['pre_riquisite' => $resolved]);
                }
            }

            $locked->update([
                'status' => 'completed', 'course_id' => $course->id,
                'confirmed_by_user_id' => $actor->id, 'confirmed_at' => now(),
            ]);

            return ['status' => 'completed', 'course_id' => $course->id, 'created' => $created, 'updated' => $updated];
        });
    }

    /** @param array<string, mixed> $selection */
    private function validateSelection(CurriculumImport $import, User $actor, array $selection): void
    {
        $this->assertWritesEnabled();
        $course = null;
        if ($selection['mode'] === 'existing') {
            $course = Course::query()->where('school_id', $import->school_id)->findOrFail($selection['course_id']);
            $this->permit($actor, 'Update:Course');
        } else {
            $this->permit($actor, 'Create:Course');
            if (Course::query()->where('code', $selection['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'Program code already exists. Select the existing program instead.']);
            }
            $normalize = static fn (string $text): string => mb_strtolower(mb_trim(preg_replace('/\s+/u', ' ', $text)));
            $normalizedTitle = $normalize($selection['title']);
            if (Course::query()->where('school_id', $import->school_id)->get(['title'])->contains(fn (Course $candidate): bool => $normalize($candidate->title) === $normalizedTitle)) {
                throw ValidationException::withMessages(['title' => 'A program with this title already exists at this school. Select it to update instead.']);
            }
            if (! Department::query()->where('school_id', $import->school_id)->whereKey($selection['department_id'])->exists()) {
                throw ValidationException::withMessages(['department_id' => 'Select a department from this school.']);
            }
            if (! \App\Models\CourseType::query()->whereKey($selection['course_type_id'])->exists()) {
                throw ValidationException::withMessages(['course_type_id' => 'Select a valid course type.']);
            }
        }

        $codes = [];
        $needsUpdate = false;
        $needsCreate = false;
        $included = [];
        foreach ($import->draft['rows'] as $index => $raw) {
            $row = $selection['rows'][$index];
            if ($row['skip'] ?? false) {
                continue;
            }
            foreach (['units', 'lecture', 'laboratory'] as $field) {
                if (! isset($row[$field])) {
                    throw ValidationException::withMessages(['rows' => "Enter {$field} for {$raw['code']} before importing."]);
                }
            }
            if (! ($row['hours_confirmed'] ?? false)) {
                throw ValidationException::withMessages(['rows' => "Confirm the lecture and laboratory hours for {$raw['code']} before importing."]);
            }
            $code = mb_strtoupper(mb_trim($row['code']));
            if (isset($codes[$code])) {
                throw ValidationException::withMessages(['rows' => "Duplicate subject code {$code} at row {$raw['row']}. Resolve or skip one row."]);
            }
            $codes[$code] = true;
            $subject = Subject::query()->where('code', $code)->first();
            if ($subject && ($course === null || $subject->course_id !== $course->id)) {
                throw ValidationException::withMessages(['rows' => "Subject code {$code} belongs to a different program. Choose its program or change the code."]);
            }
            $needsUpdate = $needsUpdate || $subject !== null;
            $needsCreate = $needsCreate || $subject === null;
            $included[$code] = $row;
        }
        if ($codes === []) {
            throw ValidationException::withMessages(['rows' => 'Choose at least one subject to import.']);
        }
        if ($needsUpdate) {
            $this->permit($actor, 'Update:Subject');
        }
        if ($needsCreate) {
            $this->permit($actor, 'Create:Subject');
        }
        $edges = [];
        foreach ($selection['rows'] as $index => $row) {
            if ($row['skip'] ?? false) {
                continue;
            }
            $self = mb_strtoupper(mb_trim($row['code']));
            $edges[$self] = [];
            foreach ($this->prerequisites($row['prerequisites'] ?? '') as $code) {
                if ($code === $self || ! isset($codes[$code])) {
                    throw ValidationException::withMessages(['rows' => "Prerequisite {$code} for {$self} is invalid or not included in this import."]);
                }
                $edges[$self][] = $code;
            }
        }
        $visited = [];
        $inPath = [];
        $visit = function (string $code) use (&$visit, &$visited, &$inPath, $edges): void {
            if (isset($inPath[$code])) {
                throw ValidationException::withMessages(['rows' => "Prerequisite cycle detected at {$code}."]);
            }
            if (isset($visited[$code])) {
                return;
            }
            $inPath[$code] = true;
            foreach ($edges[$code] as $next) {
                $visit($next);
            }
            unset($inPath[$code]);
            $visited[$code] = true;
        };
        foreach (array_keys($included) as $code) {
            $visit($code);
        }
    }

    /** @return list<string> */
    private function prerequisites(string $value): array
    {
        return array_values(array_filter(array_map(static fn (string $code): string => mb_strtoupper(mb_trim($code)), preg_split('/[,;]+/', $value) ?: [])));
    }

    private function schoolId(): int
    {
        return $this->tenants->getCurrentSchool()?->id ?? throw ValidationException::withMessages(['school' => 'Select a school before importing.']);
    }

    private function assertSchool(CurriculumImport $import): void
    {
        abort_unless($import->school_id === $this->schoolId(), 404);
    }

    private function assertWritesEnabled(): void
    {
        abort_unless(app(GeneralSettingsService::class)->isMcpWriteEnabled(), 403, 'MCP data modifications are disabled.');
    }

    private function permit(User $actor, string $permission): void
    {
        abort_unless($actor->canAccessAdminPortal() && ($actor->hasRole('super_admin') || $actor->can($permission)), 403);
    }
}
