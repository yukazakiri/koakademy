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
use Illuminate\Support\Collection;

/**
 * Shared behaviour for the portal global (command palette) searches.
 *
 * Every portal searches a different slice of the database, but they agree on how
 * a query is normalised, how wide each result group may be, and how a record is
 * shaped for the palette. Keeping those rules here stops the portals drifting
 * apart — they previously disagreed on result shape, on the matching engine, and
 * on whether the academic period was applied at all.
 *
 * Constraints are always applied through the Scout `query()` callback. Scout
 * skips `where()` / `whereIn()` constraints as soon as a `query()` callback is
 * registered, so mixing the two silently drops the filters.
 */
final class GlobalSearchService
{
    /** Queries shorter than this return nothing: one letter matches everything. */
    public const int MIN_QUERY_LENGTH = 2;

    /** Rows per group when the palette shows every group at once. */
    public const int RESULT_LIMIT = 5;

    /** Rows when the palette is narrowed to one group. */
    public const int EXPANDED_RESULT_LIMIT = 20;

    /** @var list<string> */
    public const array ADMINISTRATOR_GROUPS = ['students', 'classes', 'users', 'faculty', 'enrollments'];

    /** @var list<string> */
    public const array FACULTY_GROUPS = ['classes', 'students'];

    /** @var list<string> */
    public const array STUDENT_GROUPS = ['subjects', 'classes', 'courses', 'enrollments'];

    /** How many matching students we look up when resolving related records. */
    private const int RELATED_MATCH_LIMIT = 50;

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
            $results['students'] = $this->administratorStudents($query, $limit);
        }

        if ($type === 'all' || $type === 'classes') {
            $results['classes'] = $this->administratorClasses($query, $limit);
        }

        if ($type === 'all' || $type === 'users') {
            $results['users'] = $this->users($query, $limit);
        }

        if ($type === 'all' || $type === 'faculty') {
            $results['faculty'] = $this->faculty($query, $limit);
        }

        if ($type === 'all' || $type === 'enrollments') {
            $results['enrollments'] = $this->administratorEnrollments($query, $limit);
        }

        return $results;
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
            $results['classes'] = $this->facultyClasses($faculty, $query, $limit);
        }

        if ($type === 'all' || $type === 'students') {
            $results['students'] = $this->facultyStudents($faculty, $query, $limit);
        }

        return $results;
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

        $limit = $this->limitFor($type);

        if ($type === 'all' || $type === 'subjects') {
            $results['subjects'] = $this->subjects($query, $limit);
        }

        if ($type === 'all' || $type === 'classes') {
            $results['classes'] = $this->studentClasses($user, $query, $limit);
        }

        if ($type === 'all' || $type === 'courses') {
            $results['courses'] = $this->courses($query, $limit);
        }

        if ($type === 'all' || $type === 'enrollments') {
            $results['enrollments'] = $this->studentEnrollments($user, $query, $limit);
        }

        return $results;
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
     * @return list<array<string, mixed>>
     */
    private function administratorStudents(string $query, int $limit): array
    {
        return Student::search($query)
            ->query(fn (Builder $builder): Builder => $builder->with(['Course']))
            ->take($limit)
            ->get()
            ->map(function (Student $student): array {
                $type = $student->student_type;
                $status = $student->status;

                return $this->studentSummary($student) + [
                    'type' => $type instanceof StudentType ? $type->value : (is_string($type) ? $type : null),
                    'status' => $status instanceof StudentStatus ? $status->value : (is_string($status) ? $status : null),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function administratorClasses(string $query, int $limit): array
    {
        return $this->classResults(
            $query,
            $limit,
            fn (Builder $builder): Builder => $builder
                ->currentAcademicPeriod()
                ->with([
                    'Subject',
                    'SubjectByCodeFallback',
                    'ShsSubject',
                    'faculty',
                    'Room',
                ])
                ->withCount('class_enrollments'),
            fn (Classes $class): array => $this->classSummary($class) + [
                'faculty' => $class->faculty?->full_name ?? 'TBA',
                'students_count' => (int) ($class->class_enrollments_count ?? 0),
                'maximum_slots' => (int) ($class->maximum_slots ?? 0),
                'room' => $class->Room?->name ?? 'TBA',
                'courses' => $class->formatted_course_codes ?? 'N/A',
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function users(string $query, int $limit): array
    {
        return User::search($query)
            ->take($limit)
            ->get()
            ->map(fn (User $user): array => $this->userSummary($user))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function faculty(string $query, int $limit): array
    {
        return Faculty::search($query)
            ->take($limit)
            ->get()
            ->map(fn (Faculty $faculty): array => $this->facultySummary($faculty))
            ->values()
            ->all();
    }

    /**
     * Enrollments are matched through their student: the palette row is labelled
     * with the student's name, and enrollment columns themselves are not
     * meaningful search terms.
     *
     * @return list<array<string, mixed>>
     */
    private function administratorEnrollments(string $query, int $limit): array
    {
        $studentIds = Student::search($query)
            ->take(self::RELATED_MATCH_LIMIT)
            ->get()
            ->modelKeys();

        if ($studentIds === []) {
            return [];
        }

        return StudentEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->currentAcademicPeriod()
            ->with(['student', 'course'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (StudentEnrollment $enrollment): array => $this->enrollmentSummary($enrollment))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facultyClasses(Faculty $faculty, string $query, int $limit): array
    {
        return $this->classResults(
            $query,
            $limit,
            fn (Builder $builder): Builder => $builder
                ->where('faculty_id', $faculty->id)
                ->currentAcademicPeriod()
                ->with(['Subject', 'SubjectByCodeFallback', 'ShsSubject']),
            fn (Classes $class): array => $this->classSummary($class),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facultyStudents(Faculty $faculty, string $query, int $limit): array
    {
        $taughtClassIds = fn (Builder $builder): Builder => $builder
            ->where('faculty_id', $faculty->id)
            ->currentAcademicPeriod();

        $students = Student::search($query)
            ->query(fn (Builder $builder): Builder => $builder
                ->whereHas('classEnrollments.class', $taughtClassIds)
                ->with(['Course'])
                ->withCount([
                    'classEnrollments as class_count' => fn (Builder $enrollmentQuery): Builder => $enrollmentQuery
                        ->whereHas('class', $taughtClassIds),
                ])
                ->orderBy('last_name')
                ->orderBy('first_name'))
            ->take($limit)
            ->get();

        $sectionLabels = $this->sectionLabelsFor($students, $faculty);

        return $students
            ->map(fn (Student $student): array => $this->studentSummary($student) + [
                'class_count' => (int) ($student->class_count ?? 0),
                'sections' => $sectionLabels->get($student->id, []),
            ])
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
     * @return list<array<string, mixed>>
     */
    private function studentClasses(User $user, string $query, int $limit): array
    {
        $studentId = $this->studentIdFor($user);

        // A student may only discover classes they are enrolled in.
        if ($studentId === null) {
            return [];
        }

        return $this->classResults(
            $query,
            $limit,
            fn (Builder $builder): Builder => $builder
                ->whereHas('class_enrollments', fn (Builder $enrollmentQuery): Builder => $enrollmentQuery
                    ->where('student_id', $studentId))
                ->with(['Subject', 'SubjectByCodeFallback', 'ShsSubject']),
            fn (Classes $class): array => $this->classSummary($class),
        );
    }

    /**
     * Class search shared by every portal.
     *
     * Scout can only match real columns, so classes are first matched on their own
     * columns and then topped up with classes whose subject code or title matches —
     * people search "capstone", not "IT401".
     *
     * @param  Closure(Builder): Builder  $scope
     * @param  Closure(Classes): array<string, mixed>  $map
     * @return list<array<string, mixed>>
     */
    private function classResults(string $query, int $limit, Closure $scope, Closure $map): array
    {
        $matches = Classes::search($query)
            ->query($scope)
            ->take($limit)
            ->get();

        if ($matches->count() < $limit) {
            $matches = $matches->concat(
                $this->classesMatchingSubject($query, $limit - $matches->count(), $scope, $matches->modelKeys()),
            );
        }

        return $matches->take($limit)->map($map)->values()->all();
    }

    /**
     * Classes whose subject code or title contains the query phrase.
     *
     * @param  Closure(Builder): Builder  $scope
     * @param  list<int|string>  $excludeIds
     * @return Collection<int, Classes>
     */
    private function classesMatchingSubject(string $query, int $limit, Closure $scope, array $excludeIds): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        $like = '%'.$query.'%';

        $matchesSubject = fn (Builder $subjectQuery): Builder => $subjectQuery
            ->where('code', 'like', $like)
            ->orWhere('title', 'like', $like);

        return Classes::query()
            ->where(fn (Builder $builder): Builder => $builder
                ->whereHas('Subject', $matchesSubject)
                ->orWhereHas('SubjectByCodeFallback', $matchesSubject)
                ->orWhereHas('ShsSubject', $matchesSubject))
            ->when($excludeIds !== [], fn (Builder $builder): Builder => $builder->whereNotIn('id', $excludeIds))
            ->tap($scope)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function studentEnrollments(User $user, string $query, int $limit): array
    {
        $studentId = $this->studentIdFor($user);

        if ($studentId === null) {
            return [];
        }

        return StudentEnrollment::search($query)
            ->query(fn (Builder $builder): Builder => $builder
                ->where('student_id', $studentId)
                ->with(['student', 'course']))
            ->take($limit)
            ->get()
            ->map(fn (StudentEnrollment $enrollment): array => $this->enrollmentSummary($enrollment))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function subjects(string $query, int $limit): array
    {
        return Subject::search($query)
            ->take($limit)
            ->get()
            ->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function courses(string $query, int $limit): array
    {
        return Course::search($query)
            ->take($limit)
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'department' => $course->department,
            ])
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
}
