<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Shared behaviour for the portal global (command palette) searches.
 *
 * Every portal searches a different slice of the [REDACTED], but they agree on how
 * a query is normalised, how wide each result group may be, and how a record is
 * shaped for the palette. Keeping those rules here stops the portals drifting
 * apart — they previously disagreed on result shape, on the matching engine, and
 * on whether the academic period was applied at all.
 *
 * The palette previews: `search*` returns a few rows per group. The full-page
 * results views paginate: `paginate*` returns one group as a paginator. Both
 * paths shape rows through the same builder, so a row's `url` — the one place a
 * destination is decided — is identical in the palette and on the results page.
 *
 * Constraints are always applied through the Scout `query()` callback. Scout
 * skips `where()` / `whereIn()` constraints as soon as a `query()` callback is
 * registered, so mixing the two silently drops the filters.
 */
final class GlobalSearchService
{
    /** Queries shorter than this return nothing: one letter matches everything. */
    public const int MIN_QUERY_LENGTH = 2;

    /** Rows per group when a portal previews every group at once. */
    public const int RESULT_LIMIT = 5;

    /** Rows when a portal endpoint is narrowed to a single group. */
    public const int EXPANDED_RESULT_LIMIT = 20;

    /** @var list<string> */
    public const array ADMINISTRATOR_GROUPS = ['students', 'classes', 'users', 'faculty', 'enrollments', 'courses'];

    /** @var list<string> */
    public const array FACULTY_GROUPS = ['classes', 'students'];

    /** @var list<string> */
    public const array STUDENT_GROUPS = ['subjects', 'classes', 'courses', 'enrollments'];

    /** How many matching students we look up when resolving related records. */
    private const int RELATED_MATCH_LIMIT = 50;

    /**
     * The faculty record behind a portal user.
     *
     * Faculty accounts are linked by email; there is no `user_id` column.
     */
    public function facultyFor(User $user): ?Faculty
    {
        return Faculty::query()->where('email', $user->email)->first();
    }

    /**
     * Trim and validate a raw query string.
     */
    public function normalizeQuery(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $query = mb_trim($raw);

        return mb_strlen($query) < self::MIN_QUERY_LENGTH ? null : $query;
    }

    /**
     * Rows allowed for a group, given the requested result type.
     */
    public function limitFor(string $type): int
    {
        return $type === 'all' ? self::RESULT_LIMIT : self::EXPANDED_RESULT_LIMIT;
    }

    /**
     * Resolve the requested result type, ignoring unknown values.
     *
     * @param  list<string>  $groups
     */
    public function resolveType(mixed $raw, array $groups): string
    {
        return is_string($raw) && in_array($raw, $groups, true) ? $raw : 'all';
    }

    /**
     * An empty response with every group present, so clients always see a stable shape.
     *
     * @param  list<string>  $groups
     * @return array<string, list<array<string, mixed>>>
     */
    public function emptyPayload(array $groups): array
    {
        return array_fill_keys($groups, []);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function searchAdministrator(?string $query, string $type): array
    {
        $results = $this->emptyPayload(self::ADMINISTRATOR_GROUPS);

        if ($query === null) {
            return $results;
        }

        $limit = $this->limitFor($type);

        if ($type === 'all' || $type === 'students') {
            $results['students'] = Student::search($query)
                ->query($this->administratorStudentScope())
                ->take($limit)
                ->get()
                ->map(fn (Student $student): array => $this->administratorStudentRow($student))
                ->values()
                ->all();
        }

        if ($type === 'all' || $type === 'classes') {
            $results['classes'] = $this->classResults(
                $query,
                $limit,
                $this->administratorClassScope(),
                fn (Classes $class): array => $this->administratorClassRow($class),
            );
        }

        if ($type === 'all' || $type === 'users') {
            $results['users'] = User::search($query)
                ->take($limit)
                ->get()
                ->map(fn (User $user): array => $this->administratorUserRow($user))
                ->values()
                ->all();
        }

        if ($type === 'all' || $type === 'faculty') {
            $results['faculty'] = Faculty::search($query)
                ->take($limit)
                ->get()
                ->map(fn (Faculty $faculty): array => $this->administratorFacultyRow($faculty))
                ->values()
                ->all();
        }

        if ($type === 'all' || $type === 'enrollments') {
            $results['enrollments'] = $this->administratorEnrollments($query, $limit);
        }

        if ($type === 'all' || $type === 'courses') {
            $results['courses'] = Course::search($query)
                ->take($limit)
                ->get()
                ->map(fn (Course $course): array => $this->administratorCourseRow($course))
                ->values()
                ->all();
        }

        return $results;
    }

    /**
     * Paginated results for the full-page search view.
     *
     * One group at a time, so the page can paginate honestly instead of guessing
     * how much of a mixed result set the user has already seen.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateAdministrator(?string $query, string $group, int $perPage = 20): LengthAwarePaginator
    {
        if ($query === null) {
            return $this->emptyPaginator($perPage);
        }

        return match ($group) {
            'students' => Student::search($query)
                ->query($this->administratorStudentScope())
                ->paginate($perPage)
                ->through(fn (Student $student): array => $this->administratorStudentRow($student)),
            'classes' => Classes::search($query)
                ->query($this->administratorClassScope())
                ->paginate($perPage)
                ->through(fn (Classes $class): array => $this->administratorClassRow($class)),
            'users' => User::search($query)
                ->paginate($perPage)
                ->through(fn (User $user): array => $this->administratorUserRow($user)),
            'faculty' => Faculty::search($query)
                ->paginate($perPage)
                ->through(fn (Faculty $faculty): array => $this->administratorFacultyRow($faculty)),
            'enrollments' => $this->paginateAdministratorEnrollments($query, $perPage),
            'courses' => Course::search($query)
                ->paginate($perPage)
                ->through(fn (Course $course): array => $this->administratorCourseRow($course)),
            default => $this->emptyPaginator($perPage),
        };
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function searchFaculty(Faculty $faculty, ?string $query, string $type): array
    {
        $results = $this->emptyPayload(self::FACULTY_GROUPS);

        if ($query === null) {
            return $results;
        }

        $limit = $this->limitFor($type);

        if ($type === 'all' || $type === 'classes') {
            $results['classes'] = $this->classResults(
                $query,
                $limit,
                $this->facultyClassScope($faculty),
                fn (Classes $class): array => $this->facultyClassRow($class),
            );
        }

        if ($type === 'all' || $type === 'students') {
            $students = Student::search($query)
                ->query($this->facultyStudentScope($faculty))
                ->take($limit)
                ->get();

            $results['students'] = $this->facultyStudentRows($students, $faculty);
        }

        return $results;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateFaculty(Faculty $faculty, ?string $query, string $group, int $perPage = 20): LengthAwarePaginator
    {
        if ($query === null) {
            return $this->emptyPaginator($perPage);
        }

        return match ($group) {
            'classes' => Classes::search($query)
                ->query($this->facultyClassScope($faculty))
                ->paginate($perPage)
                ->through(fn (Classes $class): array => $this->facultyClassRow($class)),
            'students' => $this->paginateFacultyStudents($faculty, $query, $perPage),
            default => $this->emptyPaginator($perPage),
        };
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function searchStudent(User $user, ?string $query, string $type): array
    {
        $results = $this->emptyPayload(self::STUDENT_GROUPS);

        if ($query === null) {
            return $results;
        }

        $studentId = $this->studentIdFor($user);

        $limit = $this->limitFor($type);

        // Subjects and courses are portal-wide; only the student's own records
        // need a linked student row.
        if ($type === 'all' || $type === 'subjects') {
            $results['subjects'] = Subject::search($query)
                ->take($limit)
                ->get()
                ->map(fn (Subject $subject): array => $this->studentSubjectRow($subject))
                ->values()
                ->all();
        }

        if (($type === 'all' || $type === 'classes') && $studentId !== null) {
            $results['classes'] = $this->classResults(
                $query,
                $limit,
                $this->studentClassScope((int) $studentId),
                fn (Classes $class): array => $this->studentClassRow($class),
            );
        }

        if ($type === 'all' || $type === 'courses') {
            $results['courses'] = Course::search($query)
                ->take($limit)
                ->get()
                ->map(fn (Course $course): array => $this->studentCourseRow($course))
                ->values()
                ->all();
        }

        if (($type === 'all' || $type === 'enrollments') && $studentId !== null) {
            $results['enrollments'] = StudentEnrollment::search($query)
                ->query($this->studentEnrollmentScope((int) $studentId))
                ->take($limit)
                ->get()
                ->map(fn (StudentEnrollment $enrollment): array => $this->studentEnrollmentRow($enrollment))
                ->values()
                ->all();
        }

        return $results;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateStudent(User $user, ?string $query, string $group, int $perPage = 20): LengthAwarePaginator
    {
        $studentId = $this->studentIdFor($user);
        $needsStudentRecord = in_array($group, ['classes', 'enrollments'], true);

        if ($query === null || ($needsStudentRecord && $studentId === null)) {
            return $this->emptyPaginator($perPage);
        }

        return match ($group) {
            'subjects' => Subject::search($query)
                ->paginate($perPage)
                ->through(fn (Subject $subject): array => $this->studentSubjectRow($subject)),
            'classes' => Classes::search($query)
                ->query($this->studentClassScope((int) $studentId))
                ->paginate($perPage)
                ->through(fn (Classes $class): array => $this->studentClassRow($class)),
            'courses' => Course::search($query)
                ->paginate($perPage)
                ->through(fn (Course $course): array => $this->studentCourseRow($course)),
            'enrollments' => StudentEnrollment::search($query)
                ->query($this->studentEnrollmentScope((int) $studentId))
                ->paginate($perPage)
                ->through(fn (StudentEnrollment $enrollment): array => $this->studentEnrollmentRow($enrollment)),
            default => $this->emptyPaginator($perPage),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function studentSummary(Student $student): array
    {
        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'name' => $student->full_name,
            'course' => $student->Course?->code,
            'course_title' => $student->Course?->title,
            'academic_year' => $student->formatted_academic_year,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function classSummary(Classes $class): array
    {
        $subject = $this->subjectFor($class);

        return [
            'id' => $class->id,
            'record_title' => $class->record_title,
            'subject_code' => $subject?->code ?? $class->subject_code ?? 'N/A',
            'subject_title' => $subject?->title ?? 'N/A',
            'section' => $class->section ?? 'N/A',
            'school_year' => $class->school_year ?? 'N/A',
            'semester' => (string) ($class->semester ?? 'N/A'),
            'classification' => $class->classification ?? 'college',
        ];
    }

    /**
     * The students an administrator search matched, with their enrollment scope.
     * Enrollment rows are resolved from these ids, so both paths agree on which
     * students count as a match.
     */
    private function administratorEnrollmentQuery(string $query): ?Builder
    {
        $studentIds = Student::search($query)
            ->take(self::RELATED_MATCH_LIMIT)
            ->get()
            ->modelKeys();

        if ($studentIds === []) {
            return null;
        }

        return StudentEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->currentAcademicPeriod()
            ->with(['student', 'course'])
            ->orderByDesc('id');
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateAdministratorEnrollments(string $query, int $perPage): LengthAwarePaginator
    {
        $enrollments = $this->administratorEnrollmentQuery($query);

        if ($enrollments === null) {
            return $this->emptyPaginator($perPage);
        }

        return $enrollments
            ->paginate($perPage)
            ->through(fn (StudentEnrollment $enrollment): array => $this->administratorEnrollmentRow($enrollment));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function administratorEnrollments(string $query, int $limit): array
    {
        $enrollments = $this->administratorEnrollmentQuery($query);

        if ($enrollments === null) {
            return [];
        }

        return $enrollments
            ->limit($limit)
            ->get()
            ->map(fn (StudentEnrollment $enrollment): array => $this->administratorEnrollmentRow($enrollment))
            ->values()
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1);
    }

    /**
     * @return Closure(Builder): Builder
     */
    private function administratorStudentScope(): Closure
    {
        return fn (Builder $builder): Builder => $builder->with(['Course']);
    }

    /**
     * @return Closure(Builder): Builder
     */
    private function administratorClassScope(): Closure
    {
        return fn (Builder $builder): Builder => $builder
            ->currentAcademicPeriod()
            ->with(['Subject', 'SubjectByCodeFallback', 'ShsSubject', 'faculty', 'Room'])
            ->withCount('class_enrollments');
    }

    /**
     * @return Closure(Builder): Builder
     */
    private function facultyClassScope(Faculty $faculty): Closure
    {
        return fn (Builder $builder): Builder => $builder
            ->where('faculty_id', $faculty->id)
            ->currentAcademicPeriod()
            ->with(['Subject', 'SubjectByCodeFallback', 'ShsSubject']);
    }

    /**
     * @return Closure(Builder): Builder
     */
    private function facultyStudentScope(Faculty $faculty): Closure
    {
        $taughtClassIds = fn (Builder $builder): Builder => $builder
            ->where('faculty_id', $faculty->id)
            ->currentAcademicPeriod();

        return fn (Builder $builder): Builder => $builder
            ->whereHas('classEnrollments.class', $taughtClassIds)
            ->with(['Course'])
            ->withCount([
                'classEnrollments as class_count' => fn (Builder $enrollmentQuery): Builder => $enrollmentQuery
                    ->whereHas('class', $taughtClassIds),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * A student may only discover the classes they are enrolled in.
     *
     * @return Closure(Builder): Builder
     */
    private function studentClassScope(int $studentId): Closure
    {
        return fn (Builder $builder): Builder => $builder
            ->whereHas('class_enrollments', fn (Builder $enrollmentQuery): Builder => $enrollmentQuery
                ->where('student_id', $studentId))
            ->with(['Subject', 'SubjectByCodeFallback', 'ShsSubject']);
    }

    /**
     * @return Closure(Builder): Builder
     */
    private function studentEnrollmentScope(int $studentId): Closure
    {
        return fn (Builder $builder): Builder => $builder
            ->where('student_id', $studentId)
            ->with(['student', 'course']);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateFacultyStudents(Faculty $faculty, string $query, int $perPage): LengthAwarePaginator
    {
        $paginator = Student::search($query)
            ->query($this->facultyStudentScope($faculty))
            ->paginate($perPage);

        // Section labels need one query for the whole page, not one per row.
        $rows = $this->facultyStudentRows($paginator->getCollection(), $faculty);

        return $paginator->setCollection(collect($rows));
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return list<array<string, mixed>>
     */
    private function facultyStudentRows(Collection $students, Faculty $faculty): array
    {
        $sectionLabels = $this->sectionLabelsFor($students, $faculty);

        return $students
            ->map(fn (Student $student): array => $this->facultyStudentRow($student, $sectionLabels->get($student->id, [])))
            ->values()
            ->all();
    }

    /**
     * Human labels ("SUBJECT - SECTION") for the classes a faculty teaches each student in.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, list<string>>
     */
    private function sectionLabelsFor(Collection $students, Faculty $faculty): Collection
    {
        $studentIds = $students->modelKeys();

        if ($studentIds === []) {
            return collect();
        }

        return ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('class', fn (Builder $builder): Builder => $builder
                ->where('faculty_id', $faculty->id)
                ->currentAcademicPeriod())
            ->with(['class:id,section,subject_code'])
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $enrollments): array => $enrollments
                ->map(function (ClassEnrollment $enrollment): string {
                    $subjectCode = $enrollment->class?->subject_code;
                    $section = $enrollment->class?->section ?? 'N/A';

                    return $subjectCode !== null && $subjectCode !== '' ? $subjectCode.' - '.$section : $section;
                })
                ->filter()
                ->unique()
                ->values()
                ->all());
    }

    /**
     * Class search shared by every portal.
     *
     * Subject code/title matching comes from `Classes::searchableRelations()`, so
     * these results paginate like any other group.
     *
     * @param  Closure(Builder): Builder  $scope
     * @param  Closure(Classes): array<string, mixed>  $map
     * @return list<array<string, mixed>>
     */
    private function classResults(string $query, int $limit, Closure $scope, Closure $map): array
    {
        return Classes::search($query)
            ->query($scope)
            ->take($limit)
            ->get()
            ->map($map)
            ->values()
            ->all();
    }

    /**
     * Resolve the student record behind a portal user.
     *
     * Students are linked to their user either by `students.user_id` or, for older
     * records, by matching email — the same two keys the rest of the portal uses.
     */
    private function studentIdFor(User $user): ?int
    {
        $student = Student::query()
            ->where(fn (Builder $builder): Builder => $builder
                ->where('user_id', $user->id)
                ->orWhere('email', $user->email))
            ->first();

        return $student?->id === null ? null : (int) $student->id;
    }

    /**
     * Resolve the subject a class actually runs, handling SHS classes and the
     * legacy code-based fallback in one place.
     */
    private function subjectFor(Classes $class): ?object
    {
        $subject = $class->subjects->first();

        if ($subject) {
            return $subject;
        }

        return $class->isShs()
            ? $class->ShsSubject
            : ($class->Subject ?: $class->SubjectByCodeFallback);
    }

    /**
     * @return array<string, mixed>
     */
    private function administratorStudentRow(Student $student): array
    {
        $type = $student->student_type;
        $status = $student->status;

        return $this->studentSummary($student) + [
            'type' => $type instanceof StudentType ? $type->value : (is_string($type) ? $type : null),
            'status' => $status instanceof StudentStatus ? $status->value : (is_string($status) ? $status : null),
            'url' => route('administrators.students.show', $student),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function administratorClassRow(Classes $class): array
    {
        return $this->classSummary($class) + [
            'faculty' => $class->faculty?->full_name ?? 'TBA',
            'students_count' => (int) ($class->class_enrollments_count ?? 0),
            'maximum_slots' => (int) ($class->maximum_slots ?? 0),
            'room' => $class->Room?->name ?? 'TBA',
            'courses' => $class->formatted_course_codes ?? 'N/A',
            'url' => route('administrators.classes.show', $class),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function administratorUserRow(User $user): array
    {
        return $this->userSummary($user) + ['url' => route('administrators.users.edit', $user)];
    }

    /**
     * @return array<string, mixed>
     */
    private function administratorFacultyRow(Faculty $faculty): array
    {
        return $this->facultySummary($faculty) + ['url' => route('administrators.faculties.show', $faculty)];
    }

    /**
     * @return array<string, mixed>
     */
    private function administratorEnrollmentRow(StudentEnrollment $enrollment): array
    {
        return $this->enrollmentSummary($enrollment) + ['url' => route('administrators.enrollments.show', $enrollment)];
    }

    /**
     * Programs are what an administrator can open from a course hit.
     *
     * @return array<string, mixed>
     */
    private function administratorCourseRow(Course $course): array
    {
        return $this->courseSummary($course) + ['url' => route('administrators.curriculum.programs.show', $course)];
    }

    /**
     * @return array<string, mixed>
     */
    private function facultyClassRow(Classes $class): array
    {
        return $this->classSummary($class) + ['url' => route('faculty.classes.show', $class)];
    }

    /**
     * @param  list<string>  $sections
     * @return array<string, mixed>
     */
    private function facultyStudentRow(Student $student, array $sections): array
    {
        return $this->studentSummary($student) + [
            'class_count' => (int) ($student->class_count ?? 0),
            'sections' => $sections,
            'url' => route('faculty.students.show', $student),
        ];
    }

    /**
     * Subjects have no page of their own in the student portal, so a hit opens the
     * class list filtered to that subject code.
     *
     * @return array<string, mixed>
     */
    private function studentSubjectRow(Subject $subject): array
    {
        return $this->subjectSummary($subject) + [
            'url' => route('student.classes.index', ['search' => $subject->code]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentClassRow(Classes $class): array
    {
        return $this->classSummary($class) + ['url' => route('student.classes.show', $class)];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentCourseRow(Course $course): array
    {
        return $this->courseSummary($course) + [
            'url' => route('student.classes.index', ['search' => $course->code]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentEnrollmentRow(StudentEnrollment $enrollment): array
    {
        return $this->enrollmentSummary($enrollment) + ['url' => route('student.tuition.index')];
    }

    /**
     * @return array<string, mixed>
     */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->getLabel() ?? 'User',
            'avatar' => $user->avatar_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function facultySummary(Faculty $faculty): array
    {
        return [
            'id' => $faculty->id,
            'name' => $faculty->full_name,
            'email' => $faculty->email,
            'department' => $faculty->department,
            'avatar' => $faculty->photo_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentSummary(StudentEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'student_name' => $enrollment->student?->full_name ?? 'Unknown',
            'course_code' => $enrollment->course?->code ?? 'N/A',
            'year_level' => (string) ($enrollment->academic_year ?? 'N/A'),
            'status' => $enrollment->status,
            'school_year' => $enrollment->school_year,
            'semester' => (string) $enrollment->semester,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectSummary(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'code' => $subject->code,
            'title' => $subject->title,
            'units' => $subject->units,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseSummary(Course $course): array
    {
        return [
            'id' => $course->id,
            'code' => $course->code,
            'title' => $course->title,
            'department' => $course->department?->code,
        ];
    }
}
