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

final class ClassSeeder extends Seeder
{
    /**
     * The class offerings to seed.
     *
     * Every entry names its subject, the programs it belongs to, its section,
     * its room and how the faculty is picked. Nothing is referenced by
     * database id: the subject, the programs, the room and the faculty are
     * all resolved by code at seed time, so the seeder survives a change to
     * the ids the other seeders happen to use.
     *
     * A definition whose subject or whose programs no longer exist is skipped
     * and reported, rather than taking the whole seed down.
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

        // First Year General Education
        ['subject' => 'MATH111', 'courses' => ['BSIT', 'BSBA-MM', 'BSHM'], 'section' => 'A', 'room' => 'Room 101', 'faculty' => ['department' => 'General Education', 'offset' => 0], 'slots' => 40],
        ['subject' => 'ENG111', 'courses' => ['BSIT', 'BSBA-MM', 'BSHM'], 'section' => 'A', 'room' => 'Room 102', 'faculty' => ['department' => 'General Education', 'offset' => 1], 'slots' => 40],

        // PATHFIT is open to every year of every program.
        ['subject' => 'PATHFIT1', 'courses' => ['BSIT', 'BSBA-MM', 'BSHM', 'BSCS', 'BSBA-FM'], 'section' => 'A', 'room' => 'Auditorium', 'faculty' => ['department' => 'General Education', 'offset' => 0], 'slots' => 50],
        ['subject' => 'PATHFIT1', 'courses' => ['BSIT', 'BSBA-MM', 'BSHM', 'BSCS', 'BSBA-FM'], 'section' => 'B', 'room' => 'Auditorium', 'faculty' => ['department' => 'General Education', 'offset' => 1], 'slots' => 50],

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

        // Business Administration
        ['subject' => 'BA111', 'courses' => ['BSBA-MM'], 'section' => 'A', 'room' => 'Business Lab', 'faculty' => ['department' => 'Business Administration', 'offset' => 0], 'slots' => 35],
        ['subject' => 'BA112', 'courses' => ['BSBA-MM'], 'section' => 'A', 'room' => 'Marketing Lab', 'faculty' => ['department' => 'Business Administration', 'offset' => 0], 'slots' => 35],
        ['subject' => 'ACC111', 'courses' => ['BSBA-MM', 'BSBA-FM'], 'section' => 'A', 'room' => 'Accounting Lab', 'faculty' => ['department' => 'Business Administration', 'offset' => 1], 'slots' => 40],

        // Hotel Management
        ['subject' => 'HM111', 'courses' => ['BSHM'], 'section' => 'A', 'room' => 'Room 301', 'faculty' => ['department' => 'Hotel Management', 'offset' => 0], 'slots' => 30],
        ['subject' => 'HM112', 'courses' => ['BSHM'], 'section' => 'A', 'room' => 'Kitchen Lab', 'faculty' => ['department' => 'Hotel Management', 'offset' => 1], 'slots' => 25],
        ['subject' => 'HM113', 'courses' => ['BSHM'], 'section' => 'A', 'room' => 'Front Office Lab', 'faculty' => ['department' => 'Hotel Management', 'offset' => 0], 'slots' => 25],
    ];

    /**
     * The three classes the demo faculty teaches. They are the ones the
     * faculty walkthrough and the demo enrollment block rely on.
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
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;

        $settings = app(GeneralSettingsService::class);
        $schoolYear = $settings->getCurrentSchoolYearString();
        $semester = $settings->getCurrentSemester();

        $subjects = Subject::query()->get()->keyBy('code');
        $courses = Course::query()->get()->keyBy('code');
        $rooms = Room::query()->get()->keyBy('name');
        $faculties = Faculty::query()->get();

        $created = 0;
        $skipped = [];

        foreach (self::CLASS_DEFINITIONS as $definition) {
            $outcome = $this->seedClass($definition, $subjects, $courses, $rooms, $faculties, $schoolId, $schoolYear, $semester);

            if ($outcome === true) {
                $created++;

                continue;
            }

            $skipped[] = $outcome;
        }

        $demoFaculty = $faculties->firstWhere('email', self::DEMO_FACULTY_EMAIL);

        if ($demoFaculty !== null) {
            foreach (self::DEMO_CLASS_DEFINITIONS as $definition) {
                $outcome = $this->seedClass(
                    [...$definition, 'faculty' => ['department' => null, 'offset' => 0]],
                    $subjects,
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
     * @param  Collection<string, Subject>  $subjects
     * @param  Collection<string, Course>  $courses
     * @param  Collection<string, Room>  $rooms
     * @param  Collection<int, Faculty>  $faculties
     * @param  array{subject: string, courses: list<string>, section: string, room: string, faculty: array{department: string|null, offset: int}, slots: int}  $definition
     * @return true|string True when the class was created, otherwise the reason it was skipped.
     */
    private function seedClass(
        array $definition,
        Collection $subjects,
        Collection $courses,
        Collection $rooms,
        Collection $faculties,
        int $schoolId,
        string $schoolYear,
        int $semester,
        ?Faculty $demoFaculty = null,
    ): true|string {
        $subject = $subjects->get($definition['subject']);

        if (! $subject instanceof Subject) {
            return sprintf('the subject %s does not exist.', $definition['subject']);
        }

        $courseIds = collect($definition['courses'])
            ->map(fn (string $code): ?int => $courses->get($code)?->id)
            ->filter(fn (?int $id): bool => $id !== null)
            ->values()
            ->all();

        if ($courseIds === []) {
            return sprintf('none of the programs [%s] for %s exist.', implode(', ', $definition['courses']), $definition['subject']);
        }

        $alreadySeeded = Classes::query()
            ->where('subject_code', $subject->code)
            ->where('section', $definition['section'])
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->exists();

        if ($alreadySeeded) {
            return sprintf('%s section %s is already seeded for %s.', $subject->code, $definition['section'], $schoolYear);
        }

        $faculty = $demoFaculty ?? $this->resolveFaculty($faculties, $definition['faculty']['department'], $definition['faculty']['offset']);
        $room = $rooms->get($definition['room']);

        Classes::query()->create([
            'subject_id' => $subject->id,
            'subject_ids' => [$subject->id],
            'subject_code' => $subject->code,
            'faculty_id' => $faculty?->id,
            'academic_year' => $subject->academic_year,
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
     * Pick the nth faculty member of a department.
     *
     * @param  Collection<int, Faculty>  $faculties
     */
    private function resolveFaculty(Collection $faculties, ?string $department, int $offset): ?Faculty
    {
        if ($department === null) {
            return null;
        }

        $candidates = $faculties->where('department', $department)->values();

        return $candidates->get($offset);
    }
}
