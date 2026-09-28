<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\ClassEnrollment;
use App\Models\Course;
use App\Models\Student;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Structured, filterable queries against the student directory.
 *
 * The AI copilot tools and the MCP server both need to answer institutional
 * questions such as "give me all emails of enrolled BSIT students this
 * semester" or "how many 2nd year applicants are there". A free-text search
 * alone cannot express those questions, so every surface delegates here to
 * keep one definition of what a program, a term, and "enrolled this term" mean.
 *
 * Supported enrollment bases for term-scoped queries:
 *  - `enrollment` (default): the student has a `student_enrollment` record for
 *    the term that did not terminate in an abandoned outcome. This is the
 *    auditable definition used by the registrar.
 *  - `class`: the student has an active `class_enrollment` in a class running
 *    the term.
 *  - `status`: the student profile status alone is treated as active
 *    (`enrolled` or `on_leave`). Ignores term activity, so only use it when
 *    the caller explicitly wants the roster flag rather than term enrollment.
 *  - `any`: no term constraint at all.
 */
final class StudentDirectoryQuery
{
    /**
     * Terminal outcomes that mean the student abandoned the term enrollment.
     *
     * `completed` is deliberately absent: a completed enrollment record is
     * still a record of the student having enrolled in that term.
     */
    public const array ABANDONED_TERMINAL_OUTCOMES = [
        'cancelled',
        'canceled',
        'rejected',
        'withdrawn',
        'void',
    ];

    public const string BASIS_ENROLLMENT = 'enrollment';

    public const string BASIS_CLASS = 'class';

    public const string BASIS_STATUS = 'status';

    public const string BASIS_ANY = 'any';

    public const int DEFAULT_LIMIT = 50;

    public const int MAX_LIMIT = 500;

    /**
     * Upper bound on how many names a single pasted list may carry, so one
     * request cannot ask the model to resolve an unbounded set of lookups.
     */
    public const int MAX_BATCH_SIZE = 150;

    public function __construct(
        private readonly GeneralSettingsService $settingsService,
    ) {}

    /**
     * Resolve the requested academic term, defaulting to the current one.
     *
     * @return array{school_year: string, semester: int, school_year_variants: array<int, string>}
     */
    public function resolveTerm(?string $schoolYear = null, ?int $semester = null): array
    {
        $resolvedYear = filled($schoolYear)
            ? GeneralSettingsService::normalizeSchoolYear((string) $schoolYear)
            : $this->settingsService->getCurrentSchoolYearString();

        $resolvedSemester = $semester ?? $this->settingsService->getCurrentSemester();

        return [
            'school_year' => $resolvedYear,
            'semester' => (int) $resolvedSemester,
            'school_year_variants' => $this->schoolYearVariants($resolvedYear),
        ];
    }

    /**
     * Human readable label for a term, used in tool output and agent guidance.
     */
    public function termLabel(string $schoolYear, int $semester): string
    {
        return sprintf('%s · %s', $schoolYear, $this->semesterLabel($semester));
    }

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        return array_map(
            static fn (StudentStatus $status): string => $status->value,
            StudentStatus::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public function studentTypes(): array
    {
        return array_map(
            static fn (StudentType $type): string => $type->value,
            StudentType::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public function genders(): array
    {
        return array_map(
            static fn (Gender $gender): string => $gender->value,
            Gender::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public function enrollmentBases(): array
    {
        return [self::BASIS_ENROLLMENT, self::BASIS_CLASS, self::BASIS_STATUS, self::BASIS_ANY];
    }

    /**
     * The list of programs an agent can filter by, so it can recover from a
     * near-miss program name instead of reporting "no such program".
     *
     * @return list<array{id: int, code: string, title: string, student_count: int}>
     */
    public function availablePrograms(): array
    {
        return Course::query()
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(fn (Course $course): array => [
                'id' => (int) $course->id,
                'code' => (string) $course->code,
                'title' => (string) $course->title,
                'student_count' => $course->studentCount(),
            ])
            ->all();
    }

    /**
     * Resolve free-text program references (codes, titles, department codes) to
     * concrete courses. An exact case-insensitive code match always wins; only
     * when nothing matches exactly do we fall back to a partial match.
     *
     * @param  array<int, mixed>  $terms
     * @return list<array{id: int, code: string, title: string}>
     */
    public function resolvePrograms(array $terms): array
    {
        $normalized = collect($terms)
            ->map(static fn ($term): string => mb_strtoupper(mb_trim((string) $term)))
            ->filter(static fn (string $term): bool => $term !== '')
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        $exact = Course::query()
            ->whereIn('code', $normalized->all())
            ->get()
            ->keyBy('code');

        $resolved = [];

        foreach ($normalized as $term) {
            $course = $exact->get($term);

            if ($course instanceof Course) {
                $resolved[(int) $course->id] = $this->programSummary($course);

                continue;
            }

            $partial = Course::query()
                ->where(function (Builder $query) use ($term): void {
                    $like = '%'.$term.'%';
                    $query->whereRaw('UPPER(code) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(title) LIKE ?', [$like])
                        ->orWhereHas(
                            'department',
                            static fn (Builder $dept): Builder => $dept->whereRaw('UPPER(code) LIKE ?', [$like]),
                        );
                })
                ->orderBy('code')
                ->first();

            if ($partial instanceof Course) {
                $resolved[(int) $partial->id] = $this->programSummary($partial);
            }
        }

        return array_values($resolved);
    }

    /**
     * Resolve a pasted list of names into one row per entry, marking the ones
     * that matched nothing.
     *
     * Both the copilot tool and the MCP server route through here so a
     * graduation list resolves identically on either surface.
     *
     * @param  array<int, mixed>  $identifiers
     * @return array{count: int, found_count: int, students: list<array<string, mixed>>}
     */
    public function resolveBatch(array $identifiers): array
    {
        $term = $this->resolveTerm();
        $rows = [];
        $found = 0;

        foreach ($identifiers as $raw) {
            if (! is_string($raw) && ! is_numeric($raw)) {
                continue;
            }

            $label = mb_trim((string) $raw);
            $clean = $this->stripListRowNumber($label);

            if ($clean === '' || $this->isListPreamble($clean)) {
                continue;
            }

            $student = $this->resolveIdentifier($clean);

            if (! $student instanceof Student) {
                // Report the miss explicitly rather than dropping the entry, so a
                // roster lookup cannot silently lose a person.
                $rows[] = [
                    'query' => $label,
                    'found' => false,
                    'id' => null,
                    'student_id' => null,
                    'student_number' => null,
                    'name' => null,
                    'email' => null,
                ];

                continue;
            }

            $found++;

            $rows[] = [
                'query' => $label,
                'found' => true,
                // `student_id` is the identifier the previous name-lookup tool
                // returned; kept so existing consumers keep resolving students.
                'student_id' => (string) $student->student_id,
            ] + $this->present(
                collect([$student]),
                'summary',
                self::BASIS_ANY,
                $term,
            )[0];
        }

        return [
            'count' => count($rows),
            'found_count' => $found,
            'students' => $rows,
        ];
    }

    /**
     * Split a pasted block of names into individual lookups.
     *
     * Registrar lists arrive as free text, one name per line, often with a
     * leading row number ("1  CRUZ, JUAN D."). Header lines and list preambles
     * are dropped so a copied table does not turn into bogus lookups.
     *
     * @return list<string>
     */
    public function splitNameList(?string $raw): array
    {
        if (! is_string($raw) || ! str_contains($raw, "\n") && ! str_contains($raw, "\r")) {
            return [];
        }

        $lines = array_map('trim', preg_split('/[\r\n]+/', $raw) ?: []);

        return array_values(array_filter(array_map(
            fn (string $line): string => $this->stripListRowNumber($line),
            $lines,
        ), fn (string $line): bool => filled($line) && ! $this->isListPreamble($line)));
    }

    /**
     * Resolve one identifier to a single student.
     *
     * Stricter than the free-text filter used by `execute()`: this is the path
     * a pasted list takes, where a wrong match is worse than no match. A
     * formatted "LAST, FIRST M." entry is never allowed to fall back to a
     * surname-only match, because that would silently attach the wrong person
     * to a graduation or clearance list.
     */
    public function resolveIdentifier(string $identifier): ?Student
    {
        $clean = mb_trim($identifier);

        if ($clean === '') {
            return null;
        }

        $base = fn (): Builder => Student::query()->with('course:id,code,title', 'course.department:id,code');

        if (str_contains($clean, '@')) {
            return $base()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($clean)])
                ->first();
        }

        if (is_numeric($clean)) {
            return $base()
                ->where(function (Builder $query) use ($clean): void {
                    $query->where('student_id', $clean)
                        ->orWhere('lrn', $clean)
                        ->orWhere('id', (int) $clean);
                })
                ->first();
        }

        if (str_contains($clean, ',')) {
            [$last, $rest] = explode(',', $clean, 2);
            $last = mb_strtolower(mb_trim($last));
            $given = mb_trim($rest);
            // Registrars write "CRUZ, JUAN D."; the trailing initial must not
            // be part of the given name we match on.
            $given = mb_trim(preg_replace('/\s+[A-Za-z]\.?$/u', '', $given) ?? $given);
            $firstWord = mb_strtolower(explode(' ', $given)[0] ?? '');

            if ($last !== '' && $firstWord !== '') {
                return $base()
                    ->whereRaw('LOWER(last_name) LIKE ?', ['%'.$last.'%'])
                    ->whereRaw('LOWER(first_name) LIKE ?', ['%'.$firstWord.'%'])
                    ->first();
            }

            return $last === '' ? null : $base()->whereRaw('LOWER(last_name) LIKE ?', ['%'.$last.'%'])->first();
        }

        $words = preg_split('/\s+/u', $clean) ?: [];

        if (count($words) < 2) {
            $term = mb_strtolower($words[0] ?? '');

            return $base()
                ->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('LOWER(last_name) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$term.'%']);
                })
                ->first();
        }

        $first = mb_strtolower($words[0]);
        $last = mb_strtolower($words[count($words) - 1]);

        return $base()
            ->where(function (Builder $query) use ($first, $last): void {
                $query->where(function (Builder $forward) use ($first, $last): void {
                    $forward->whereRaw('LOWER(first_name) LIKE ?', ['%'.$first.'%'])
                        ->whereRaw('LOWER(last_name) LIKE ?', ['%'.$last.'%']);
                })->orWhere(function (Builder $reversed) use ($first, $last): void {
                    // "Bunalan Renelyn" is as common in a pasted roster as
                    // "Renelyn Bunalan".
                    $reversed->whereRaw('LOWER(first_name) LIKE ?', ['%'.$last.'%'])
                        ->whereRaw('LOWER(last_name) LIKE ?', ['%'.$first.'%']);
                });
            })
            ->first();
    }

    /**
     * Run a directory query.
     *
     * @param  array{
     *     query?: string|null,
     *     program?: array<int, string>|null,
     *     course_id?: int|null,
     *     status?: array<int, string>|null,
     *     year_level?: array<int, int>|null,
     *     student_type?: array<int, string>|null,
     *     gender?: string|null,
     *     basis?: string|null,
     *     school_year?: string|null,
     *     semester?: int|null,
     *     limit?: int|null,
     *     offset?: int|null,
     *     fields?: string|null,
     * }  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters = []): array
    {
        $basis = $this->normalizeBasis($filters['basis'] ?? null, $filters);
        $fields = $this->normalizeFields($filters['fields'] ?? 'summary');
        $term = $this->resolveTerm($filters['school_year'] ?? null, $filters['semester'] ?? null);

        $statuses = $this->normalizeEnumValues($filters['status'] ?? [], $this->statuses());
        $studentTypes = $this->normalizeEnumValues($filters['student_type'] ?? [], $this->studentTypes());
        $requestedGender = $this->normalizeFreeText($filters['gender'] ?? null);
        $genders = $requestedGender === null || $requestedGender === 'unspecified'
            ? []
            : $this->normalizeEnumValues([$requestedGender], $this->genders());
        $includeUnspecifiedGender = $requestedGender === 'unspecified';

        $courseId = isset($filters['course_id']) ? (int) $filters['course_id'] : null;
        $requestedPrograms = (array) ($filters['program'] ?? []);
        $programs = $courseId === null
            ? $this->resolvePrograms($requestedPrograms)
            : [];
        $unresolvedPrograms = $courseId === null
            ? $this->unresolvedProgramTerms($requestedPrograms, $programs)
            : [];

        if ($courseId === null && $requestedPrograms !== [] && $programs === []) {
            return $this->unresolvedProgramPayload($unresolvedPrograms, $basis, $term, $fields);
        }

        $yearLevels = collect($filters['year_level'] ?? [])
            ->map(static fn ($level): int => (int) $level)
            ->filter(static fn (int $level): bool => $level >= 1 && $level <= 5)
            ->unique()
            ->values()
            ->all();

        $limit = $this->normalizeLimit($filters['limit'] ?? null);
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $query = $this->buildQuery(
            freeText: $this->normalizeFreeText($filters['query'] ?? null),
            courseId: $courseId,
            programs: $programs,
            statuses: $statuses,
            yearLevels: $yearLevels,
            studentTypes: $studentTypes,
            genders: $genders,
            includeUnspecifiedGender: $includeUnspecifiedGender,
            basis: $basis,
            term: $term,
        );

        // Count and page as separate statements: the AI surfaces page with a raw
        // offset, and paginate() only understands page numbers, which silently
        // truncates the result set once the offset is not a multiple of the
        // limit.
        $total = (clone $query)->toBase()->getCountForPagination();

        $students = $query
            ->offset($offset)
            ->limit($limit)
            ->get();

        $emails = $this->collectEmails($students);

        return [
            'term' => [
                'school_year' => $term['school_year'],
                'semester' => $term['semester'],
                'label' => $this->termLabel($term['school_year'], $term['semester']),
                'enrollment_basis' => $basis,
                'basis_explanation' => $this->basisExplanation($basis),
            ],
            'criteria' => [
                'query' => $this->normalizeFreeText($filters['query'] ?? null),
                'program' => $requestedPrograms,
                'unresolved_programs' => $unresolvedPrograms,
                'course_id' => $courseId,
                'status' => $statuses,
                'year_level' => $yearLevels,
                'student_type' => $studentTypes,
                'gender' => $genders,
                'unspecified_gender_included' => $includeUnspecifiedGender,
            ],
            'resolved_programs' => $programs,
            'total_matched' => $total,
            'returned' => $students->count(),
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => $offset + $students->count() < $total,
            'next_offset' => $offset + $students->count(),
            'students_missing_email' => $students->count() - count($emails),
            'fields' => $fields,
            'emails' => $fields === 'emails' ? $emails : null,
            'students' => $fields === 'emails' ? [] : $this->present($students, $fields, $basis, $term),
        ];
    }

    /**
     * @param  list<array{id: int, code: string, title: string}>  $programs
     * @param  array{school_year: string, semester: int, school_year_variants: array<int, string>}  $term
     * @return Builder<Student>
     */
    private function buildQuery(
        ?string $freeText,
        ?int $courseId,
        array $programs,
        array $statuses,
        array $yearLevels,
        array $studentTypes,
        array $genders,
        bool $includeUnspecifiedGender,
        string $basis,
        array $term,
    ): Builder {
        $query = Student::query()
            ->with('course:id,code,title', 'course.department:id,code');

        if ($freeText !== null) {
            $like = '%'.addcslashes($freeText, '%_\\').'%';

            $query->where(function (Builder $nested) use ($freeText, $like): void {
                $nested->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('student_id', 'like', $like)
                    ->orWhere('lrn', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereRaw(
                        "LOWER(TRIM(CONCAT_WS(' ', first_name, middle_name, last_name, suffix))) LIKE ?",
                        [mb_strtolower($like)],
                    );

                // A formatted registrar name ("CRUZ, JUAN D.") matches no single
                // column, so add the surname + given-name pairing. Both parts
                // are required: falling back to surname alone would attach the
                // wrong person to a roster.
                $pairing = $this->surnameGivenPairing($freeText);

                if ($pairing !== null) {
                    $nested->orWhere(function (Builder $paired) use ($pairing): void {
                        $paired->whereRaw('LOWER(last_name) LIKE ?', ['%'.$pairing[0].'%'])
                            ->whereRaw('LOWER(first_name) LIKE ?', ['%'.$pairing[1].'%']);
                    });
                }
            });
        }

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        } elseif ($programs !== []) {
            $query->whereIn('course_id', array_map(
                static fn (array $program): int => $program['id'],
                $programs,
            ));
        }

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($yearLevels !== []) {
            $query->whereIn('academic_year', $yearLevels);
        }

        if ($studentTypes !== []) {
            $query->whereIn('student_type', $studentTypes);
        }

        if ($genders !== [] || $includeUnspecifiedGender) {
            $query->where(function (Builder $nested) use ($genders, $includeUnspecifiedGender): void {
                foreach ($genders as $gender) {
                    $nested->orWhereRaw('LOWER(TRIM(COALESCE(gender, \'\'))) = ?', [$gender]);
                }

                if ($includeUnspecifiedGender) {
                    $nested->orWhere(function (Builder $blank): void {
                        $blank->whereNull('gender')->orWhereRaw("TRIM(COALESCE(gender, '')) = ''");
                    });
                }
            });
        }

        match ($basis) {
            self::BASIS_CLASS => $this->applyClassBasis($query, $term),
            self::BASIS_STATUS => $this->applyStatusBasis($query),
            self::BASIS_ANY => null,
            default => $this->applyEnrollmentBasis($query, $term),
        };

        return $query->orderBy('last_name')->orderBy('first_name')->orderBy('id');
    }

    /**
     * @param  array{school_year: string, semester: int, school_year_variants: array<int, string>}  $term
     */
    private function applyEnrollmentBasis(Builder $query, array $term): void
    {
        $query->whereExists(function ($sub) use ($term): void {
            $sub->selectRaw('1')
                ->from('student_enrollment')
                ->whereIn('school_year', $term['school_year_variants'])
                ->where('semester', $term['semester'])
                ->where(function (\Illuminate\Database\Query\Builder $outcomes): void {
                    $outcomes->whereNull('terminal_outcome')
                        ->orWhereNotIn('terminal_outcome', self::ABANDONED_TERMINAL_OUTCOMES);
                })
                // The correlation is on the student's primary key, and `students`
                // is already narrowed to the caller's school by SchoolScope, so no
                // extra school predicate is needed here. Requiring the enrollment's
                // own school_id to match would silently drop legitimate rows whose
                // enrollment record predates tenant backfilling.
                ->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
        });
    }

    /**
     * @param  array{school_year: string, semester: int, school_year_variants: array<int, string>}  $term
     */
    private function applyClassBasis(Builder $query, array $term): void
    {
        // Written as an explicit EXISTS rather than whereHas on the
        // classEnrollments relation because the academic period lives on the
        // related `classes` row, and a correlated subquery states that join
        // (and the tenant match) without depending on scope resolution.
        $query->whereExists(function ($sub) use ($term): void {
            $sub->selectRaw('1')
                ->from('class_enrollments')
                ->join('classes', 'classes.id', '=', 'class_enrollments.class_id')
                ->whereColumn('class_enrollments.student_id', 'students.id')
                ->where('class_enrollments.status', true)
                ->whereIn('classes.school_year', $term['school_year_variants'])
                ->where('classes.semester', $term['semester']);
        });
    }

    private function applyStatusBasis(Builder $query): void
    {
        $query->whereIn('status', [
            StudentStatus::Enrolled->value,
            StudentStatus::OnLeave->value,
        ]);
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return list<string>
     */
    private function collectEmails(Collection $students): array
    {
        return $students
            ->map(static fn (Student $student): ?string => $student->email)
            ->filter(static fn (?string $email): bool => filled($email))
            ->map(static fn (string $email): string => mb_strtolower(mb_trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  array{school_year: string, semester: int, school_year_variants: array<int, string>}  $term
     * @return list<array<string, mixed>>
     */
    private function present(Collection $students, string $fields, string $basis, array $term): array
    {
        $detailed = $fields === 'detailed';
        $termScoped = in_array($basis, [self::BASIS_ENROLLMENT, self::BASIS_CLASS], true);

        $rows = [];

        /** @var Student $student */
        foreach ($students as $student) {
            $course = $student->Course;

            $row = [
                'id' => (int) $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'program_code' => $course instanceof Course ? $course->code : null,
                'program_title' => $course instanceof Course ? $course->title : null,
                'department' => $course instanceof Course ? $course->department()->value('code') : null,
                'year_level' => $student->academic_year,
                // The model casts both to backed enums; the raw value is the
                // fallback so an unrecognised legacy row still serialises.
                'student_type' => $this->enumValue($student->getAttribute('student_type')),
                'status' => $this->enumValue($student->getAttribute('status')),
                // True is guaranteed by the query itself for the two term-scoped
                // bases; null means "not determined" otherwise.
                'enrolled_this_term' => $termScoped ? true : null,
            ];

            if ($detailed) {
                $row['gender'] = $student->gender;
                $row['lrn'] = $student->lrn;
                $row['phone'] = $student->phone;
                $row['academic_period'] = $this->termLabel($term['school_year'], $term['semester']);

                if ($basis === self::BASIS_CLASS) {
                    $row['class_count_this_term'] = $this->classCountForTerm($student, $term);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Normalise a cast attribute to its scalar value.
     */
    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * @param  array{school_year: string, semester: int, school_year_variants: array<int, string>}  $term
     */
    private function classCountForTerm(Student $student, array $term): int
    {
        return ClassEnrollment::query()
            ->where('class_enrollments.student_id', $student->id)
            ->where('class_enrollments.status', true)
            ->whereIn('classes.school_year', $term['school_year_variants'])
            ->where('classes.semester', $term['semester'])
            ->join('classes', 'classes.id', '=', 'class_enrollments.class_id')
            ->count();
    }

    /**
     * Program terms the caller asked for that we could not map to a course, so
     * the agent can retry with a valid code instead of reporting no data.
     *
     * @param  array<int, mixed>  $requested
     * @param  list<array{id: int, code: string, title: string}>  $resolved
     * @return list<string>
     */
    private function unresolvedProgramTerms(array $requested, array $resolved): array
    {
        $resolvedKeys = collect($resolved)
            ->flatMap(static fn (array $program): array => [
                mb_strtoupper($program['code']),
                mb_strtoupper($program['title']),
            ])
            ->all();

        return collect($requested)
            ->map(static fn ($term): string => mb_strtoupper(mb_trim((string) $term)))
            ->filter(static fn (string $term): bool => $term !== '')
            ->reject(static fn (string $term): bool => in_array($term, $resolvedKeys, true))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $unresolved
     * @param  array{school_year: string, semester: int, school_year_variants: array<int, string>}  $term
     * @return array<string, mixed>
     */
    private function unresolvedProgramPayload(array $unresolved, string $basis, array $term, string $fields): array
    {
        return [
            'error' => 'unknown_program',
            'message' => 'No academic program matched: '.implode(', ', $unresolved).'.',
            'hint' => 'Retry with one of the available_programs codes, or drop the program filter.',
            'available_programs' => $this->availablePrograms(),
            'term' => [
                'school_year' => $term['school_year'],
                'semester' => $term['semester'],
                'label' => $this->termLabel($term['school_year'], $term['semester']),
                'enrollment_basis' => $basis,
                'basis_explanation' => $this->basisExplanation($basis),
            ],
            'criteria' => ['program' => $unresolved],
            'resolved_programs' => [],
            'total_matched' => 0,
            'returned' => 0,
            'offset' => 0,
            'limit' => self::DEFAULT_LIMIT,
            'has_more' => false,
            'next_offset' => 0,
            'students_missing_email' => 0,
            'fields' => $fields,
            'emails' => null,
            'students' => [],
        ];
    }

    private function basisExplanation(string $basis): string
    {
        return match ($basis) {
            self::BASIS_CLASS => 'Students with at least one active class enrollment in a class running this term.',
            self::BASIS_STATUS => 'Students whose profile status is enrolled or on leave, regardless of term activity.',
            self::BASIS_ANY => 'All students in the directory; no term constraint applied.',
            default => 'Students with a term enrollment record that did not end in a cancelled, rejected, withdrawn, or void outcome.',
        };
    }

    /**
     * @return array{id: int, code: string, title: string}
     */
    private function programSummary(Course $course): array
    {
        return [
            'id' => (int) $course->id,
            'code' => (string) $course->code,
            'title' => (string) $course->title,
        ];
    }

    /**
     * Resolve the enrollment basis.
     *
     * A bare free-text lookup ("find Juan Cruz") is not a population question,
     * so it must not silently drop students who have no enrollment record for
     * the current term. Only a request that actually asks about a term-scoped
     * population gets the term-scoped default.
     *
     * @param  array<string, mixed>  $filters
     */
    private function normalizeBasis(mixed $basis, array $filters = []): string
    {
        $normalized = mb_strtolower(mb_trim((string) $basis));

        if ($normalized !== '') {
            return in_array($normalized, $this->enrollmentBases(), true)
                ? $normalized
                : self::BASIS_ENROLLMENT;
        }

        $isPopulationRequest = ($filters['program'] ?? []) !== []
            || ($filters['course_id'] ?? null) !== null
            || ($filters['status'] ?? []) !== []
            || ($filters['year_level'] ?? []) !== []
            || ($filters['student_type'] ?? []) !== []
            || ($filters['gender'] ?? null) !== null
            || ($filters['school_year'] ?? null) !== null
            || ($filters['semester'] ?? null) !== null;

        if ($this->normalizeFreeText($filters['query'] ?? null) === null || $isPopulationRequest) {
            return self::BASIS_ENROLLMENT;
        }

        return self::BASIS_ANY;
    }

    /**
     * Split a "LAST, FIRST M." entry into a surname and given-name fragment.
     *
     * @return array{0: string, 1: string}|null
     */
    private function surnameGivenPairing(string $freeText): ?array
    {
        if (! str_contains($freeText, ',')) {
            return null;
        }

        [$last, $rest] = explode(',', $freeText, 2);
        $last = mb_strtolower(mb_trim($last));
        $given = mb_trim(preg_replace('/\s+[A-Za-z]\.?$/u', '', mb_trim($rest)) ?? mb_trim($rest));
        $firstWord = mb_strtolower(explode(' ', $given)[0] ?? '');

        return $last !== '' && $firstWord !== ''
            ? [$last, $firstWord]
            : null;
    }

    /**
     * Drop the "1." / "2)" row numbers registrar spreadsheets carry.
     */
    private function stripListRowNumber(string $line): string
    {
        return mb_trim(preg_replace('/^\d+[\s\.\)\-]+\s*/u', '', $line) ?? $line);
    }

    /**
     * Whether a line is a list header or preamble rather than a person.
     */
    private function isListPreamble(string $line): bool
    {
        $upper = mb_strtoupper($line);

        foreach (['BACHELOR', 'LIST', 'BATCH', 'NAME', 'STUDENT'] as $prefix) {
            if (str_starts_with($upper, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeFields(mixed $fields): string
    {
        $normalized = mb_strtolower(mb_trim((string) ($fields ?? 'summary')));

        return in_array($normalized, ['summary', 'detailed', 'emails'], true)
            ? $normalized
            : 'summary';
    }

    private function normalizeLimit(mixed $limit): int
    {
        if (! is_numeric($limit)) {
            return self::DEFAULT_LIMIT;
        }

        return (int) max(1, min(self::MAX_LIMIT, (int) $limit));
    }

    private function normalizeFreeText(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = mb_trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Keep only values that exist in the model's enum, so a near-miss label
     * degrades to "no filter" instead of silently returning an empty roster.
     *
     * @param  array<int, mixed>  $values
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function normalizeEnumValues(array $values, array $allowed): array
    {
        $permitted = collect($allowed)
            ->map(static fn (string $value): string => mb_strtolower($value))
            ->all();

        return collect($values)
            ->map(static fn ($value): string => mb_strtolower(mb_trim((string) $value)))
            ->filter(static fn (string $value): bool => in_array($value, $permitted, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function schoolYearVariants(string $schoolYear): array
    {
        $normalized = GeneralSettingsService::normalizeSchoolYear($schoolYear);

        return array_values(array_unique([$normalized, str_replace(' ', '', $normalized)]));
    }

    private function semesterLabel(int $semester): string
    {
        return match ($semester) {
            1 => '1st Semester',
            2 => '2nd Semester',
            3 => 'Summer',
            default => "Semester {$semester}",
        };
    }
}
