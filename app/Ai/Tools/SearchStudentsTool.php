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

Notes:
- `program` accepts a program code ("BSIT"), a title fragment ("Computer Studies"), or a department code. If it returns `unknown_program`, retry with one of `available_programs`.
- Omitting `school_year` and `semester` uses the current term, which is what "this semester" means.
- `enrollment_basis` decides who counts for the term. It defaults to `enrollment` (a real term enrollment record, not just a profile flag) for any population question, and to `any` for a bare name search. Pass `status` when the user wants students whose profile status is enrolled or on leave, or `class` when they mean students who have an active class this term.
- Set `fields` to `emails` when the user wants only email addresses; it returns a compact de-duplicated `emails` array.
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

        $validated = $request->validate([
            'query' => 'nullable|string|max:100',
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

        try {
            $directory = app(StudentDirectoryQuery::class);

            return $this->encode($directory->execute([
                'query' => $validated['query'] ?? null,
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
            ]));
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
                ->description('Optional free-text match on first name, last name, student number, LRN, or email. Not needed for population questions.'),
            'fields' => $schema->string()
                ->enum(['summary', 'detailed', 'emails'])
                ->description('summary (default) = number, name, email, program, year level, status. detailed adds LRN, phone, gender, department. emails returns only a compact de-duplicated email list, which is what to use when the user asks for email addresses.'),
            'limit' => $schema->integer()
                ->description('Rows to return, 1-500. Defaults to 50. Use a higher limit for large cohorts and report the total from total_matched.'),
            'offset' => $schema->integer()
                ->description('Row offset for paging. Use next_offset from the previous response while has_more is true.'),
        ];
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
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
