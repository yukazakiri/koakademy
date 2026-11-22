<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\School;
use App\Models\Subject;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ClassSeeder extends Seeder
{
    /**
     * Curated class offerings to seed first.
     *
     * @var list<array{
     *     subject: string,
     *     courses: list<string>,
     *     section: string,
     *     room: string,
     *     faculty: array{department: string, offset: int},
     *     slots: int
     * }>
     */
    private const array CLASS_DEFINITIONS = [
        // First Year IT
        ['subject' => 'IT111', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Computer Lab 1', 'faculty' => ['department' => 'Information Technology', 'offset' => 0], 'slots' => 35],
        ['subject' => 'IT111', 'courses' => ['BSIT'], 'section' => 'B', 'room' => 'Computer Lab 2', 'faculty' => ['department' => 'Information Technology', 'offset' => 1], 'slots' => 35],
        ['subject' => 'IT112', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Programming Lab', 'faculty' => ['department' => 'Information Technology', 'offset' => 0], 'slots' => 30],

        // First Year General Education (shared across all programs that take them in Sem 1)
        ['subject' => 'MATH111', 'courses' => ['BSIT', 'BSCS', 'BSIS', 'BSEMC', 'BSBA-MM', 'BSBA-FM', 'BSBA-HRM', 'BSBA-OM', 'BSHM', 'BSTM', 'BSHRM', 'BSCA'], 'section' => 'A', 'room' => 'Room 101', 'faculty' => ['department' => 'General Education', 'offset' => 0], 'slots' => 60],
        ['subject' => 'ENG111', 'courses' => ['BSIT', 'BSCS', 'BSIS', 'BSEMC', 'BSBA-MM', 'BSHM'], 'section' => 'A', 'room' => 'Room 102', 'faculty' => ['department' => 'General Education', 'offset' => 1], 'slots' => 50],

        // PATHFIT 1 is offered to 1st year of every program
        ['subject' => 'PATHFIT1', 'courses' => ['BSIT', 'BSCS', 'BSIS', 'BSEMC', 'BSBA-MM', 'BSBA-FM', 'BSBA-HRM', 'BSBA-OM', 'BSHM', 'BSTM', 'BSHRM', 'BSCA'], 'section' => 'A', 'room' => 'Auditorium', 'faculty' => ['department' => 'General Education', 'offset' => 0], 'slots' => 60],
        ['subject' => 'PATHFIT1', 'courses' => ['BSIT', 'BSCS', 'BSIS', 'BSEMC', 'BSBA-MM', 'BSBA-FM', 'BSBA-HRM', 'BSBA-OM', 'BSHM', 'BSTM', 'BSHRM', 'BSCA'], 'section' => 'B', 'room' => 'Auditorium', 'faculty' => ['department' => 'General Education', 'offset' => 1], 'slots' => 60],

        // Second Year IT
        ['subject' => 'IT211', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Computer Lab 3', 'faculty' => ['department' => 'Information Technology', 'offset' => 0], 'slots' => 30],
        ['subject' => 'IT212', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Computer Lab 4', 'faculty' => ['department' => 'Information Technology', 'offset' => 1], 'slots' => 30],
        ['subject' => 'IT213', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Programming Lab', 'faculty' => ['department' => 'Information Technology', 'offset' => 2], 'slots' => 30],

        // Third Year IT
        ['subject' => 'IT311', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Computer Lab 1', 'faculty' => ['department' => 'Information Technology', 'offset' => 0], 'slots' => 25],
        ['subject' => 'IT312', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Network Lab', 'faculty' => ['department' => 'Information Technology', 'offset' => 1], 'slots' => 25],
        ['subject' => 'IT313', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Multimedia Lab', 'faculty' => ['department' => 'Information Technology', 'offset' => 2], 'slots' => 25],

        // Fourth Year IT
        ['subject' => 'IT411', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Computer Lab 2', 'faculty' => ['department' => 'Information Technology', 'offset' => 0], 'slots' => 20],
        ['subject' => 'IT412', 'courses' => ['BSIT'], 'section' => 'A', 'room' => 'Room 201', 'faculty' => ['department' => 'Information Technology', 'offset' => 1], 'slots' => 20],

        // Business Administration Core
        ['subject' => 'BA111', 'courses' => ['BSBA-MM', 'BSBA-FM', 'BSBA-HRM', 'BSBA-OM'], 'section' => 'A', 'room' => 'Business Lab', 'faculty' => ['department' => 'Business Administration', 'offset' => 0], 'slots' => 50],
        ['subject' => 'BA112', 'courses' => ['BSBA-MM'], 'section' => 'A', 'room' => 'Marketing Lab', 'faculty' => ['department' => 'Business Administration', 'offset' => 0], 'slots' => 35],
        ['subject' => 'ACC111', 'courses' => ['BSBA-MM', 'BSBA-FM', 'BSBA-HRM', 'BSBA-OM'], 'section' => 'A', 'room' => 'Accounting Lab', 'faculty' => ['department' => 'Business Administration', 'offset' => 1], 'slots' => 50],

        // Hotel Management Core
        ['subject' => 'HM111', 'courses' => ['BSHM', 'BSTM'], 'section' => 'A', 'room' => 'Room 301', 'faculty' => ['department' => 'Hotel Management', 'offset' => 0], 'slots' => 45],
        ['subject' => 'HM112', 'courses' => ['BSHM'], 'section' => 'A', 'room' => 'Kitchen Lab', 'faculty' => ['department' => 'Hotel Management', 'offset' => 1], 'slots' => 30],
        ['subject' => 'HM113', 'courses' => ['BSHM'], 'section' => 'A', 'room' => 'Front Office Lab', 'faculty' => ['department' => 'Hotel Management', 'offset' => 0], 'slots' => 30],
    ];

    /**
     * The three classes the demo faculty teaches.
     *
     * @var list<array{
     *     subject: string,
     *     courses: list<string>,
     *     section: string,
     *     room: string,
     *     slots: int
     * }>
     */
    private const array DEMO_CLASS_DEFINITIONS = [
        ['subject' => 'IT121', 'courses' => ['BSIT', 'BSBA-MM'], 'section' => 'DEMO-A', 'room' => 'Computer Lab 1', 'slots' => 30],
        ['subject' => 'BA111', 'courses' => ['BSBA-MM', 'BSBA-FM'], 'section' => 'DEMO-B', 'room' => 'Business Lab', 'slots' => 30],
        ['subject' => 'HM111', 'courses' => ['BSHM', 'BSTM'], 'section' => 'DEMO-C', 'room' => 'Room 301', 'slots' => 30],
    ];

    private const string DEMO_FACULTY_EMAIL = 'j.adams@koakademy.edu';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedClasses();
        });
    }

    private function seedClasses(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;

        $settings = app(GeneralSettingsService::class);
        $schoolYear = $settings->getCurrentSchoolYearString();
        $semester = $settings->getCurrentSemester();

        $allSubjects = Subject::withoutSchoolScope()->get();
        $courses = Course::withoutSchoolScope()->get()->keyBy('code');
        $rooms = Room::query()->get()->keyBy('name');
        $faculties = Faculty::query()->get();

        // Index subjects by course_id and subject code so shared GE codes resolve to the right program
        $subjectsByCourse = [];
        foreach ($allSubjects as $sub) {
            $subjectsByCourse[(int) $sub->course_id][$sub->code] = $sub;
        }

        $created = 0;
        $skipped = [];

        foreach (self::CLASS_DEFINITIONS as $definition) {
            $outcome = $this->seedClass($definition, $subjectsByCourse, $allSubjects, $courses, $rooms, $faculties, $schoolId, $schoolYear, $semester);

            if ($outcome === true) {
                $created++;

                continue;
            }

            $skipped[] = $outcome;
        }

        // Also seed class offerings for all remaining current-semester curriculum subjects across all 12 courses
        $created += $this->seedRemainingCurrentSemesterClasses(
            $allSubjects,
            $subjectsByCourse,
            $courses,
            $rooms,
            $faculties,
            $schoolId,
            $schoolYear,
            $semester,
        );

        $demoFaculty = $faculties->firstWhere('email', self::DEMO_FACULTY_EMAIL);

        if ($demoFaculty !== null) {
            foreach (self::DEMO_CLASS_DEFINITIONS as $definition) {
                $outcome = $this->seedClass(
                    [...$definition, 'faculty' => ['department' => null, 'offset' => 0]],
                    $subjectsByCourse,
                    $allSubjects,
                    $courses,
                    $rooms,
                    $faculties,
                    $schoolId,
                    $schoolYear,
                    $semester,
                    $demoFaculty,
                );

                if ($outcome === true) {
                    $created++;

                    continue;
                }

                $skipped[] = $outcome;
            }
        } else {
            $this->command?->warn(sprintf('Skipped the demo classes: no faculty with the address %s.', self::DEMO_FACULTY_EMAIL));
        }

        foreach ($skipped as $reason) {
            $this->command?->warn(sprintf('Skipped a class: %s', $reason));
        }

        $this->command?->info(sprintf('Seeded %d class(es) for %s, semester %d.', $created, $schoolYear, $semester));
    }

    /**
     * Create one class from its definition.
     *
     * @param  array<int, array<string, Subject>>  $subjectsByCourse
     * @param  Collection<int, Subject>  $allSubjects
     * @param  Collection<string, Course>  $courses
     * @param  Collection<string, Room>  $rooms
     * @param  Collection<int, Faculty>  $faculties
     * @param  array{subject: string, courses: list<string>, section: string, room: string, faculty: array{department: string|null, offset: int}, slots: int}  $definition
     */
    private function seedClass(
        array $definition,
        array $subjectsByCourse,
        Collection $allSubjects,
        Collection $courses,
        Collection $rooms,
        Collection $faculties,
        int $schoolId,
        string $schoolYear,
        int $semester,
        ?Faculty $demoFaculty = null,
    ): true|string {
        $courseIds = collect($definition['courses'])
            ->map(fn (string $code): ?int => $courses->get($code)?->id)
            ->filter(fn (?int $id): bool => $id !== null)
            ->values()
            ->all();

        if ($courseIds === []) {
            return sprintf('none of the programs [%s] for %s exist.', implode(', ', $definition['courses']), $definition['subject']);
        }

        $matchingSubjects = [];
        foreach ($courseIds as $courseId) {
            if (isset($subjectsByCourse[$courseId][$definition['subject']])) {
                $matchingSubjects[] = $subjectsByCourse[$courseId][$definition['subject']];
            }
        }

        $primarySubject = $matchingSubjects[0] ?? $allSubjects->firstWhere('code', $definition['subject']);

        if (! $primarySubject instanceof Subject) {
            return sprintf('the subject %s does not exist.', $definition['subject']);
        }

        $subjectIds = $matchingSubjects !== []
            ? array_values(array_unique(array_map(static fn (Subject $s): int => (int) $s->id, $matchingSubjects)))
            : [(int) $primarySubject->id];

        $alreadySeeded = Classes::withoutSchoolScope()
            ->where('subject_code', $primarySubject->code)
            ->where('section', $definition['section'])
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->exists();

        if ($alreadySeeded) {
            return sprintf('%s section %s is already seeded for %s.', $primarySubject->code, $definition['section'], $schoolYear);
        }

        $faculty = $demoFaculty ?? $this->resolveFaculty($faculties, $definition['faculty']['department'], $definition['faculty']['offset']);
        $room = $rooms->get($definition['room']);

        Classes::query()->create([
            'subject_id' => $primarySubject->id,
            'subject_ids' => $subjectIds,
            'subject_code' => $primarySubject->code,
            'faculty_id' => $faculty?->id,
            'academic_year' => $primarySubject->academic_year,
            'semester' => $semester,
            'school_year' => $schoolYear,
            'course_codes' => $courseIds,
            'section' => $definition['section'],
            'room_id' => $room?->id,
            'classification' => 'college',
            'maximum_slots' => $definition['slots'],
            'school_id' => $schoolId,
        ]);

        return true;
    }

    /**
     * Ensure every current-semester subject across all 12 courses has an active class offering.
     *
     * @param  Collection<int, Subject>  $allSubjects
     * @param  array<int, array<string, Subject>>  $subjectsByCourse
     * @param  Collection<string, Course>  $courses
     * @param  Collection<string, Room>  $rooms
     * @param  Collection<int, Faculty>  $faculties
     */
    private function seedRemainingCurrentSemesterClasses(
        Collection $allSubjects,
        array $subjectsByCourse,
        Collection $courses,
        Collection $rooms,
        Collection $faculties,
        int $schoolId,
        string $schoolYear,
        int $semester,
    ): int {
        $existingSubjectIds = Classes::withoutSchoolScope()
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->whereNotIn('section', ['DEMO-A', 'DEMO-B', 'DEMO-C'])
            ->get(['subject_id', 'subject_ids'])
            ->flatMap(fn (Classes $c): array => array_merge(
                $c->subject_id ? [(int) $c->subject_id] : [],
                array_map('intval', (array) $c->subject_ids),
            ))
            ->unique()
            ->flip()
            ->all();

        $currentSemSubjects = $allSubjects
            ->where('semester', $semester)
            ->filter(fn (Subject $s): bool => ! isset($existingSubjectIds[(int) $s->id]))
            ->groupBy('code');

        $roomValues = $rooms->values();
        $courseById = $courses->keyBy('id');
        $created = 0;
        $index = 0;

        foreach ($currentSemSubjects as $code => $subjectsForCode) {
            $primary = $subjectsForCode->first();
            if (! $primary instanceof Subject) {
                continue;
            }

            $courseIds = $subjectsForCode->pluck('course_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
            $subjectIds = $subjectsForCode->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
            $primaryCourseCode = (string) ($courseById->get($primary->course_id)?->code ?? 'BSIT');

            $department = $this->departmentForSubject((string) $code, $primaryCourseCode);
            $faculty = $this->resolveFaculty($faculties, $department, $index % 3) ?? $faculties->first();
            $room = $roomValues->isNotEmpty() ? $roomValues->get($index % $roomValues->count()) : null;

            $section = 'A';
            $usedSections = Classes::withoutSchoolScope()
                ->where('subject_code', $code)
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->pluck('section')
                ->all();

            if (in_array($section, $usedSections, true)) {
                $section = 'A-'.$primaryCourseCode;
            }

            Classes::query()->create([
                'subject_id' => $primary->id,
                'subject_ids' => $subjectIds,
                'subject_code' => $primary->code,
                'faculty_id' => $faculty?->id,
                'academic_year' => $primary->academic_year,
                'semester' => $semester,
                'school_year' => $schoolYear,
                'course_codes' => $courseIds,
                'section' => $section,
                'room_id' => $room?->id,
                'classification' => 'college',
                'maximum_slots' => count($courseIds) > 1 ? 60 : 40,
                'school_id' => $schoolId,
            ]);

            $created++;
            $index++;
        }

        return $created;
    }

    private function departmentForSubject(string $subjectCode, string $courseCode): string
    {
        if (str_starts_with($subjectCode, 'GE') || str_starts_with($subjectCode, 'MATH') || str_starts_with($subjectCode, 'ENG') || str_starts_with($subjectCode, 'PATHFIT') || in_array($subjectCode, ['ETHICS', 'RIZAL'], true)) {
            return 'General Education';
        }

        if (str_starts_with($courseCode, 'BSBA')) {
            return 'Business Administration';
        }

        if (in_array($courseCode, ['BSHM', 'BSTM', 'BSHRM', 'BSCA'], true)) {
            return 'Hotel Management';
        }

        return 'Information Technology';
    }

    /**
     * Pick the nth faculty member of a department.
     *
     * @param  Collection<int, Faculty>  $faculties
     */
    private function resolveFaculty(Collection $faculties, ?string $department, int $offset): ?Faculty
    {
        if ($department === null) {
            return null;
        }

        $candidates = $faculties
            ->where('department', $department)
            ->where('email', '!=', self::DEMO_FACULTY_EMAIL)
            ->values();

        if ($candidates->isEmpty()) {
            return $faculties->where('email', '!=', self::DEMO_FACULTY_EMAIL)->first();
        }

        return $candidates->get($offset % $candidates->count());
    }
}
