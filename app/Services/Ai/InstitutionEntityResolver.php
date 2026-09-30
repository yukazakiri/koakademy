<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * InstitutionEntityResolver
 *
 * AI agents frequently hold loose, human-shaped identifiers: an employee number
 * ("800188"), a compound class label ("GE-3 Section B"), a room fragment
 * ("Room 501"), or a student number instead of a database key. Passing those
 * straight into Eloquent `find()` is unsafe: `faculty.id` is a UUID, so
 * `Faculty::find('800188')` raises a Postgres "invalid input syntax for type
 * uuid" driver error instead of a usable "not found" answer.
 *
 * This resolver converts loose identifiers into real records, is always
 * school-scoped, and reports ambiguity explicitly so the agent can ask the
 * administrator to disambiguate instead of silently writing to the wrong row.
 *
 * All matching uses portable SQL (LIKE / ||) so it behaves identically on the
 * SQLite test connection and the Postgres production connection.
 */
final class InstitutionEntityResolver
{
    /**
     * Maximum candidates reported back to the agent when a lookup is ambiguous.
     */
    private const MAX_SUGGESTIONS = 5;

    /**
     * Resolve a faculty member from a UUID, employee number, email, or name.
     *
     * Resolution order (most specific first):
     *  1. exact UUID primary key
     *  2. exact `faculty_id_number` (numeric or string employee number)
     *  3. exact email
     *  4. all-tokens name match (order-insensitive)
     *
     * @throws Exceptions\EntityNotFoundException when nothing matches
     * @throws Exceptions\AmbiguousEntityException when several records match
     */
    public function faculty(mixed $identifier, ?School $school = null): Faculty
    {
        $candidates = $this->facultyCandidates($identifier, $school);

        if ($candidates === []) {
            throw Exceptions\EntityNotFoundException::for('faculty', $this->describe($identifier));
        }

        if (count($candidates) > 1) {
            throw Exceptions\AmbiguousEntityException::for('faculty', $this->describe($identifier), $this->facultyLabels($candidates));
        }

        return $candidates[0];
    }

    /**
     * Resolve a faculty member, returning null instead of throwing.
     */
    public function findFaculty(mixed $identifier, ?School $school = null): ?Faculty
    {
        try {
            return $this->faculty($identifier, $school);
        } catch (Exceptions\EntityNotFoundException|Exceptions\AmbiguousEntityException) {
            return null;
        }
    }

    /**
     * Resolve a classroom by numeric ID, exact name, or name fragment.
     *
     * @throws Exceptions\EntityNotFoundException
     * @throws Exceptions\AmbiguousEntityException
     */
    public function room(mixed $identifier, ?School $school = null): Room
    {
        $candidates = $this->roomCandidates($identifier, $school);

        if ($candidates === []) {
            throw Exceptions\EntityNotFoundException::for('room', $this->describe($identifier));
        }

        if (count($candidates) > 1) {
            throw Exceptions\AmbiguousEntityException::for('room', $this->describe($identifier), $this->roomLabels($candidates));
        }

        return $candidates[0];
    }

    /**
     * Resolve a class section by numeric ID, compound code + section, or
     * subject code alone.
     *
     * @throws Exceptions\EntityNotFoundException
     * @throws Exceptions\AmbiguousEntityException
     */
    public function class(mixed $identifier, ?string $schoolYear = null, ?int $semester = null, ?School $school = null): Classes
    {
        $candidates = $this->classCandidates($identifier, $schoolYear, $semester, $school);

        if ($candidates === []) {
            throw Exceptions\EntityNotFoundException::for('class', $this->describe($identifier));
        }

        if (count($candidates) > 1) {
            throw Exceptions\AmbiguousEntityException::for(
                'class',
                $this->describe($identifier),
                array_map(
                    fn (Classes $class): string => sprintf(
                        'class_id=%d (%s %s, SY %s Sem %s)',
                        $class->id,
                        (string) $class->subject_code,
                        (string) $class->section,
                        (string) $class->school_year,
                        (string) $class->semester,
                    ),
                    array_slice($candidates, 0, self::MAX_SUGGESTIONS),
                ),
            );
        }

        return $candidates[0];
    }

    /**
     * Resolve a student by database ID, student number, email, or full name.
     *
     * @throws Exceptions\EntityNotFoundException
     * @throws Exceptions\AmbiguousEntityException
     */
    public function student(mixed $identifier, ?School $school = null): Student
    {
        $candidates = $this->studentCandidates($identifier, $school);

        if ($candidates === []) {
            throw Exceptions\EntityNotFoundException::for('student', $this->describe($identifier));
        }

        if (count($candidates) > 1) {
            throw Exceptions\AmbiguousEntityException::for(
                'student',
                $this->describe($identifier),
                array_map(
                    fn (Student $student): string => sprintf(
                        'student_id=%s (%s)',
                        (string) $student->student_id,
                        $student->full_name,
                    ),
                    array_slice($candidates, 0, self::MAX_SUGGESTIONS),
                ),
            );
        }

        return $candidates[0];
    }

    /**
     * Resolve a curriculum subject by numeric ID, exact code, or exact title.
     */
    public function subject(mixed $identifier, ?int $courseId = null): ?Subject
    {
        return $this->subjectCandidates($identifier, $courseId)[0] ?? null;
    }

    /**
     * Ordered faculty candidate list. Exposed for callers that want to offer
     * "did you mean" alternatives rather than a hard failure.
     *
     * @return array<int, Faculty>
     */
    public function facultyCandidates(mixed $identifier, ?School $school = null): array
    {
        $raw = $this->clean($identifier);

        if ($raw === null) {
            return [];
        }

        $query = Faculty::query();
        $this->scopeToSchool($query, $school, ['school_id']);

        // 1. UUID primary key. Guarded so a numeric employee number is never
        //    handed to a uuid column (the original driver-level crash).
        if (Str::isUuid($raw)) {
            $byUuid = (clone $query)->where('id', $raw)->get();
            if ($byUuid->isNotEmpty()) {
                return $byUuid->all();
            }
        }

        // 2. Employee number — the identifier administrators actually quote.
        $byNumber = (clone $query)
            ->where(function (Builder $q) use ($raw): void {
                $q->whereRaw('LOWER(TRIM(faculty_id_number)) = ?', [mb_strtolower($raw)]);

                if (is_numeric($raw)) {
                    $q->orWhere('faculty_id_number', (int) $raw);
                }
            })
            ->get();

        if ($byNumber->isNotEmpty()) {
            return $byNumber->all();
        }

        // 3. Email.
        $byEmail = (clone $query)
            ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower($raw)])
            ->get();

        if ($byEmail->isNotEmpty()) {
            return $byEmail->all();
        }

        // 4. All-tokens name match. Order-insensitive, so both "Doc Arenas" and
        //    "Arenas, Doc Marvz R." resolve, and punctuation is ignored.
        return $this->nameMatch($query, $raw, ['first_name', 'middle_name', 'last_name']);
    }

    /**
     * @return array<int, Room>
     */
    public function roomCandidates(mixed $identifier, ?School $school = null): array
    {
        $raw = $this->clean($identifier);

        if ($raw === null) {
            return [];
        }

        $query = Room::query();
        $this->scopeToSchool($query, $school, ['school_id']);

        if (is_numeric($raw)) {
            $byId = (clone $query)->where('id', (int) $raw)->get();
            if ($byId->isNotEmpty()) {
                return $byId->all();
            }
        }

        $exact = (clone $query)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($raw)])->get();

        if ($exact->isNotEmpty()) {
            return $exact->all();
        }

        return $this->nameMatch($query, $raw, ['name'], orderBy: 'name');
    }

    /**
     * @return array<int, Classes>
     */
    public function classCandidates(mixed $identifier, ?string $schoolYear = null, ?int $semester = null, ?School $school = null): array
    {
        $raw = $this->clean($identifier);

        if ($raw === null) {
            return [];
        }

        $query = Classes::query();
        $this->scopeToSchool($query, $school, ['school_id']);

        if ($schoolYear !== null) {
            $query->whereIn('school_year', $this->schoolYearVariants($schoolYear));

            if ($semester !== null) {
                $query->where('semester', $semester);
            }
        }

        if (is_numeric($raw)) {
            $byId = (clone $query)->where('id', (int) $raw)->get();
            if ($byId->isNotEmpty()) {
                return $byId->all();
            }
        }

        [$subjectPart, $sectionPart] = $this->splitClassLabel($raw);

        if ($subjectPart === null) {
            return [];
        }

        $subject = $this->codeToken($subjectPart);
        $section = $sectionPart === null ? null : $this->codeToken($sectionPart);

        return (clone $query)
            ->where(function (Builder $q) use ($subject, $section): void {
                if ($section === null) {
                    // Bare subject code: every section of it is a candidate and
                    // the ambiguity guard makes the caller pick.
                    $q->whereRaw("LOWER(REPLACE(REPLACE(subject_code, '-', ''), ' ', '')) LIKE ?", [$subject['compactLike']])
                        ->orWhereRaw('LOWER(subject_code) LIKE ?', [$subject['like']]);

                    return;
                }

                // Both halves are named, so both must match. A loose code-only
                // match here would return every section of the subject and
                // report a bogus ambiguity for "GE-3 Section A".
                $q->where(function (Builder $sub) use ($subject, $section): void {
                    $sub->whereRaw("LOWER(REPLACE(REPLACE(subject_code, '-', ''), ' ', '')) LIKE ?", [$subject['compactLike']])
                        ->whereRaw("LOWER(REPLACE(section, '-', '')) LIKE ?", [$section['compactLike']]);
                })->orWhere(function (Builder $sub) use ($subject, $section): void {
                    $sub->whereRaw('LOWER(subject_code) LIKE ?', [$subject['like']])
                        ->whereRaw('LOWER(section) LIKE ?', [$section['like']]);
                });
            })
            ->orderByDesc('id')
            ->limit(self::MAX_SUGGESTIONS + 1)
            ->get()
            ->all();
    }

    /**
     * @return array<int, Student>
     */
    public function studentCandidates(mixed $identifier, ?School $school = null): array
    {
        $raw = $this->clean($identifier);

        if ($raw === null) {
            return [];
        }

        $query = Student::query();
        $this->scopeToSchool($query, $school, ['school_id', 'institution_id']);

        if (is_numeric($raw)) {
            $byKey = (clone $query)
                ->where(function (Builder $q) use ($raw): void {
                    $q->where('id', (int) $raw)
                        ->orWhere('student_id', $raw);
                })
                ->get();

            if ($byKey->isNotEmpty()) {
                return $byKey->all();
            }
        }

        $byEmail = (clone $query)
            ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower($raw)])
            ->get();

        if ($byEmail->isNotEmpty()) {
            return $byEmail->all();
        }

        return $this->nameMatch($query, $raw, ['first_name', 'middle_name', 'last_name'], orderBy: 'last_name');
    }

    /**
     * @return array<int, Subject>
     */
    public function subjectCandidates(mixed $identifier, ?int $courseId = null): array
    {
        $raw = $this->clean($identifier);

        if ($raw === null) {
            return [];
        }

        $query = Subject::query();

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        if (is_numeric($raw)) {
            $byId = (clone $query)->where('id', (int) $raw)->get();
            if ($byId->isNotEmpty()) {
                return $byId->all();
            }
        }

        $compact = str_replace(['-', ' ', '/'], '', mb_strtolower($raw));

        return (clone $query)
            ->where(function (Builder $q) use ($raw, $compact): void {
                $q->whereRaw('LOWER(TRIM(code)) = ?', [mb_strtolower($raw)])
                    ->orWhereRaw("LOWER(REPLACE(REPLACE(code, '-', ''), ' ', '')) = ?", [$compact])
                    ->orWhereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($raw)]);
            })
            ->orderBy('code')
            ->limit(self::MAX_SUGGESTIONS + 1)
            ->get()
            ->all();
    }

    /**
     * Both the spaced ("2026 - 2027") and compact ("2026-2027") stored forms.
     *
     * @return array<int, string>
     */
    public function schoolYearVariants(string $schoolYear): array
    {
        $normalized = GeneralSettingsService::normalizeSchoolYear($schoolYear);

        return array_values(array_unique([$normalized, str_replace(' ', '', $normalized)]));
    }

    /**
     * Split a class label into its subject code and section.
     *
     * The section is only taken from a trailing token when an explicit marker
     * ("section", "sec") or a clear trailing letter separates it. Without that
     * guard a code like "GE-3" would be read as subject "GE" plus section "3",
     * and "GE-3 Section A" as subject "GE" plus section "A" — matching GE-1 A
     * and GE-2 A as well.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitClassLabel(string $raw): array
    {
        $normalized = mb_trim((string) preg_replace('/\s+/', ' ', $raw));

        // Explicit marker: "GE-3 Section A", "GE3 sec B".
        if (preg_match('/^(.*?)\s*(?:section|sec)\.?\s+(\S+)$/iu', $normalized, $matches) === 1) {
            return [mb_trim($matches[1]), mb_trim($matches[2])];
        }

        // Trailing standalone token after a space: "GE-3 A", "BSCS 1A",
        // "GE 2 A". A hyphen inside the trailing token means it belongs to the
        // code ("GE-3-A"), not to a section.
        if (preg_match('/^(.+?)[\s]+([A-Za-z0-9]{1,4})$/u', $normalized, $matches) === 1
            && ! str_contains(mb_trim($matches[2]), '-')) {
            return [mb_trim($matches[1]), mb_trim($matches[2])];
        }

        // Bare code, or an identifier with no separable section.
        return [$this->cleanBareSubjectCode($normalized), null];
    }

    /**
     * Pre-escaped LIKE patterns for a single code or section token.
     *
     * `compactLike` drops hyphens and spaces so "GE-3" also matches a catalog
     * that stores it as "GE3" or "GE 3".
     *
     * @return array{like: string, compactLike: string}
     */
    private function codeToken(string $token): array
    {
        $lower = mb_strtolower($token);

        return [
            'like' => '%'.$this->escapeLike($lower).'%',
            'compactLike' => '%'.$this->escapeLike(str_replace(['-', ' ', '/'], '', $lower)).'%',
        ];
    }

    /**
     * Require every alphanumeric token of the identifier to appear in at least
     * one of the given columns. Portable across SQLite and Postgres and immune
     * to the "Last, First" vs "First Last" storage-order problem.
     *
     * @param  array<int, string>  $columns
     * @return array<int, mixed>
     */
    private function nameMatch(Builder $query, string $raw, array $columns, ?string $orderBy = null): array
    {
        $tokens = $this->tokens($raw);

        if ($tokens === []) {
            return [];
        }

        $result = $query;

        foreach ($tokens as $token) {
            $like = '%'.$this->escapeLike(mb_strtolower($token)).'%';

            $result->where(function (Builder $tokenQuery) use ($columns, $like): void {
                foreach ($columns as $column) {
                    $tokenQuery->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                }
            });
        }

        $result->limit(self::MAX_SUGGESTIONS + 1);

        if ($orderBy !== null) {
            $result->orderBy($orderBy);
        }

        return $result->get()->all();
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function scopeToSchool(Builder $query, ?School $school, array $columns): void
    {
        if (! $school instanceof School) {
            return;
        }

        $query->where(function (Builder $q) use ($school, $columns): void {
            foreach ($columns as $index => $column) {
                $index === 0
                    ? $q->where($column, $school->id)
                    : $q->orWhere($column, $school->id);
            }
        });
    }

    /**
     * @param  array<int, Faculty>  $faculty
     * @return array<int, string>
     */
    private function facultyLabels(array $faculty): array
    {
        return array_map(
            fn (Faculty $member): string => sprintf(
                'faculty_id_number=%s (%s)',
                (string) $member->faculty_id_number,
                $member->full_name,
            ),
            array_slice($faculty, 0, self::MAX_SUGGESTIONS),
        );
    }

    /**
     * @param  array<int, Room>  $rooms
     * @return array<int, string>
     */
    private function roomLabels(array $rooms): array
    {
        return array_map(
            fn (Room $room): string => sprintf('room_id=%d (%s)', $room->id, $room->name),
            array_slice($rooms, 0, self::MAX_SUGGESTIONS),
        );
    }

    private function clean(mixed $identifier): ?string
    {
        if ($identifier === null || is_bool($identifier) || is_array($identifier)) {
            return null;
        }

        $raw = mb_trim((string) $identifier);

        return $raw === '' ? null : $raw;
    }

    /**
     * Strip conversational noise that can wrap a bare subject code, e.g.
     * "subject GE-3" or "class GE-3". Section markers are handled separately
     * by splitClassLabel(), which needs to see them.
     */
    private function cleanBareSubjectCode(string $raw): string
    {
        $cleaned = str_ireplace(['class', 'subject'], ' ', $raw);

        return mb_trim((string) preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $raw): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($raw), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : array_values($parts);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function describe(mixed $identifier): string
    {
        $raw = $this->clean($identifier);

        return $raw === null ? '(empty)' : $raw;
    }
}
