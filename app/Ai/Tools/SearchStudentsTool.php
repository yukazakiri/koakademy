<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\StudentDirectoryQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

final class SearchStudentsTool implements Tool
{
    public function description(): Stringable|string
    {
        return <<<'DESCRIPTION'
Query the student directory with structured filters instead of guessing names. This is the correct tool for any question about a group of students, for example: "give me all emails of all enrolled BSIT students this semester", "how many 2nd year applicants do we have", "list the 3rd year BSA students", "show every student with no email address".

Use it whenever the question targets a population rather than one named person. Combine filters freely (program + status + year level + term). Every response reports the exact criteria and the total number of matches, so you can page through large cohorts with `offset` and must state the count honestly.

It also handles named lookups: a single `query` (name, "LAST, FIRST M.", student number, or email), or `queries` / `names` for a pasted roster of up to 150 names at once.

Notes:
- `program` accepts a program code ("BSIT"), a title fragment ("Computer Studies"), or a department code, and a department code returns every program in that department. If it returns `unknown_program` or `ambiguous_program`, retry with one of `available_programs`.
- Omitting `school_year` and `semester` uses the current term, which is what "this semester" means.
- `enrollment_basis` decides who counts for the term. It defaults to `enrollment` (a real term enrollment record, not just a profile flag) for any population question, and to `any` for a bare name search. Pass `status` when the user wants students whose profile status is enrolled or on leave, or `class` when they mean students who have an active class this term.
- Set `fields` to `emails` when the user wants only email addresses; it returns a compact de-duplicated `emails` array.
- A `query` containing line breaks is read as a pasted list of names and resolved one name per line. A formatted "LAST, FIRST M." entry never matches on surname alone, so an unmatched name is reported honestly as not found instead of resolving to the wrong student.
- If `has_more` is true, you have not shown every match: keep paging or say how many were returned out of the total.
DESCRIPTION;
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->canReadDirectory()) {
            return $this->encode([
                'error' => true,
                'message' => 'You are not permitted to view student directory records.',
            ]);
        }

        $directory = app(StudentDirectoryQuery::class);

        try {
            $identifiers = $this->requestedIdentifiers($request, $directory);

            if (is_array($identifiers)) {
                // A pasted list is a bulk read even when every entry is a single
                // person, so it needs the bulk-read permission rather than the
                // single-record view permission.
                if (! $this->canExportCohort()) {
                    return $this->encode($this->deniedCohort());
                }

                if (count($identifiers) > StudentDirectoryQuery::MAX_BATCH_SIZE) {
                    return $this->encode([
                        'error' => true,
                        'message' => 'The batch search limit is '.StudentDirectoryQuery::MAX_BATCH_SIZE.' names per request. Please split your list into batches of '.StudentDirectoryQuery::MAX_BATCH_SIZE.' or fewer.',
                        'count' => count($identifiers),
                        'limit' => StudentDirectoryQuery::MAX_BATCH_SIZE,
                    ]);
                }

                return $this->encode($directory->resolveBatch($identifiers));
            }

            $validated = $request->validate([
                'query' => 'nullable|string|max:500',
                'program' => 'nullable|array|max:20',
                'program.*' => 'string|max:100',
                'course_id' => 'nullable|integer|min:1',
                'status' => 'nullable|array|max:10',
                'status.*' => 'string|max:50',
                'year_level' => 'nullable|array|max:10',
                'year_level.*' => 'integer|between:1,5',
                'student_type' => 'nullable|array|max:10',
                'student_type.*' => 'string|max:50',
                'gender' => 'nullable|string|max:50',
                'enrollment_basis' => 'nullable|string|in:enrollment,class,status,any',
                'school_year' => 'nullable|string|max:20',
                'semester' => 'nullable|integer|between:1,3',
                'fields' => 'nullable|string|in:summary,detailed,emails',
                'limit' => 'nullable|integer|min:1|max:500',
                'offset' => 'nullable|integer|min:0',
            ]);

            // Any structured filter turns this from "look up the student I
            // named" into "export a cohort", which is a bulk read. A bare
            // name stays available to callers who may only view one record,
            // because that is how they find the record they are allowed to open.
            if ($this->isCohortRequest($validated) && ! $this->canExportCohort()) {
                return $this->encode($this->deniedCohort());
            }

            return $this->encode($this->present($directory->execute([
                'query' => $identifiers ?? ($validated['query'] ?? null),

                'program' => $validated['program'] ?? null,
                'course_id' => $validated['course_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'year_level' => $validated['year_level'] ?? null,
                'student_type' => $validated['student_type'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'basis' => $validated['enrollment_basis'] ?? null,
                'school_year' => $validated['school_year'] ?? null,
                'semester' => $validated['semester'] ?? null,
                'fields' => $validated['fields'] ?? null,
                'limit' => $validated['limit'] ?? null,
                'offset' => $validated['offset'] ?? null,
            ])));
        } catch (Throwable $e) {
            return $this->encode([
                'error' => true,
                'message' => "The student directory query failed: {$e->getMessage()}",
            ]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'program' => $schema->array()
                ->items($schema->string())
                ->description('Degree programs to filter by, e.g. ["BSIT"] or ["BSIT","BSCS"]. Accepts a program code, a title fragment ("Computer Studies"), or a department code. Omit to include every program.'),
            'status' => $schema->array()
                ->items($schema->string())
                ->description('Student profile statuses: applicant, enrolled, on_leave, withdrawn, dropped, graduated, transferred. Omit for all statuses.'),
            'year_level' => $schema->array()
                ->items($schema->integer())
                ->description('Year levels to include, e.g. [1,2] for first and second year.'),
            'student_type' => $schema->array()
                ->items($schema->string())
                ->description('Student types: college, shs, tesda, dhrt.'),
            'gender' => $schema->string()
                ->description('male, female, other, prefer_not_to_say, or unspecified to include blank values.'),
            'enrollment_basis' => $schema->string()
                ->enum(['enrollment', 'class', 'status', 'any'])
                ->description('How to decide who counts for the term. Defaults to "enrollment" (a term enrollment record that was not cancelled) for any population question, and to "any" for a bare name search. "class" = has an active class this term. "status" = profile status is enrolled/on leave, term ignored. "any" = no term constraint.'),
            'school_year' => $schema->string()
                ->description('School year filter, e.g. "2025-2026". Omit for the current school year, which is what "this semester" uses.'),
            'semester' => $schema->integer()
                ->description('Semester filter: 1, 2, or 3 for summer. Omit for the current semester.'),
            'query' => $schema->string()
                ->description('Optional free-text match on first name, last name, student number, LRN, or email, or a formatted name ("CRUZ, JUAN D."). A value containing line breaks is treated as a pasted list of names, one per line. Not needed for population questions.'),
            'queries' => $schema->array()
                ->items($schema->string())
                ->description('Resolve a pasted roster of names in one call, up to '.StudentDirectoryQuery::MAX_BATCH_SIZE.' entries. Returns one row per entry with found true or false, so unmatched names are visible rather than dropped.'),
            'names' => $schema->array()
                ->items($schema->string())
                ->description('Alias for queries.'),
            'fields' => $schema->string()
                ->enum(['summary', 'detailed', 'emails'])
                ->description('summary (default) = number, name, email, program, year level, status. detailed adds LRN, phone, gender, department. emails returns only a compact de-duplicated email list, which is what to use when the user asks for email addresses.'),
            'limit' => $schema->integer()
                ->description('Rows to return, 1-500. Defaults to 50. Use a higher limit for large cohorts and report the total from total_matched.'),
            'offset' => $schema->integer()
                ->description('Row offset for paging. Use next_offset from the previous response while has_more is true.'),
        ];
    }

    /**
     * Decide whether the caller asked for a batch of names or a single query.
     *
     * Returns the list of names to resolve, the single-query string, or null
     * when the request is purely filter-driven.
     */
    private function requestedIdentifiers(Request $request, StudentDirectoryQuery $directory): array|string|null
    {
        $identifiers = $request['queries'] ?? $request['names'] ?? null;

        if (is_array($identifiers) && $identifiers !== []) {
            return $identifiers;
        }

        $query = $request['query'] ?? null;

        if (! is_string($query) && ! is_numeric($query)) {
            return null;
        }

        $split = $directory->splitNameList((string) $query);

        return count($split) > 1 ? $split : mb_trim((string) $query);
    }

    private function canReadDirectory(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('View:Student')
            || $user->can('ViewAny:Student');
    }

    /**
     * Whether the caller may read many students at once.
     *
     * `View:Student` alone is deliberately not enough. Roles such as security
     * guard are seeded with `View:Student` but not `ViewAny:Student` because
     * they verify one person at a time; letting them filter by program and page
     * through the roster would hand them a bulk export of the whole directory
     * through the chat box. This mirrors the MCP tool, which already requires
     * `ViewAny:Student`.
     */
    private function canExportCohort(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->can('ViewAny:Student');
    }

    /**
     * Whether the request asks for a population rather than a named person.
     *
     * @param  array<string, mixed>  $filters
     */
    private function isCohortRequest(array $filters): bool
    {
        foreach (['program', 'course_id', 'status', 'year_level', 'student_type', 'gender', 'school_year', 'semester', 'enrollment_basis', 'fields'] as $key) {
            if (($filters[$key] ?? null) !== null) {
                return true;
            }
        }

        // Paging is only meaningful across a population; a bare name lookup
        // returns the one match and stops.
        return ($filters['offset'] ?? null) !== null;
    }

    /**
     * @return array{error: true, message: string, required_permission: string}
     */
    private function deniedCohort(): array
    {
        return [
            'error' => true,
            'message' => 'You can look up a student by name, but exporting a filtered cohort requires the ViewAny:Student permission. Ask an administrator for access, or narrow the request to one named student.',
            'required_permission' => 'ViewAny:Student',
        ];
    }

    /**
     * Add `count` alongside the paging keys so callers that predate
     * total_matched keep a single number to report.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function present(array $result): array
    {
        $result['count'] = $result['returned'] ?? 0;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
