<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Services\StudentDirectoryQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Query the student directory with structured filters: degree program, enrollment status, year level, student type, gender, and academic term. Use this instead of guessing names when a question targets a group of students, such as listing all emails of enrolled BSIT students this semester.')]
#[IsReadOnly]
final class SearchStudentsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'ViewAny:Student', 'You are not permitted to search student records.');

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', 'array', 'max:20'],
            'program.*' => ['string', 'max:100'],
            'course_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'array', 'max:10'],
            'status.*' => ['string', 'max:50'],
            'year_level' => ['nullable', 'array', 'max:10'],
            'year_level.*' => ['integer', 'between:1,5'],
            'student_type' => ['nullable', 'array', 'max:10'],
            'student_type.*' => ['string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'enrollment_basis' => ['nullable', 'string', 'in:enrollment,class,status,any'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'between:1,3'],
            'fields' => ['nullable', 'string', 'in:summary,detailed,emails'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = app(StudentDirectoryQuery::class)->execute([
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
        ]);

        return Response::structured($this->present($result, (string) ($validated['fields'] ?? 'summary')));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'program' => $schema->array()->items($schema->string())
                ->description('Degree programs to filter by, e.g. ["BSIT"]. Accepts a program code, a title fragment, or a department code.'),
            'status' => $schema->array()->items($schema->string())
                ->description('Student profile statuses: applicant, enrolled, on_leave, withdrawn, dropped, graduated, transferred.'),
            'year_level' => $schema->array()->items($schema->integer())
                ->description('Year levels to include, e.g. [1,2].'),
            'student_type' => $schema->array()->items($schema->string())
                ->description('Student types: college, shs, tesda, dhrt.'),
            'gender' => $schema->string()
                ->description('male, female, other, prefer_not_to_say, or unspecified to include blank values.'),
            'enrollment_basis' => $schema->string()
                ->description('How to decide who counts for the term: "enrollment" (default, a term enrollment record that was not cancelled), "class" (has an active class this term), "status" (profile status enrolled/on leave, term ignored), or "any" (no term constraint).'),
            'school_year' => $schema->string()
                ->description('School year filter, e.g. "2025-2026". Defaults to the current school year.'),
            'semester' => $schema->integer()
                ->description('Semester filter: 1, 2, or 3 for summer. Defaults to the current semester.'),
            'query' => $schema->string()
                ->description('Optional free-text match on name, student number, LRN, or email.'),
            'fields' => $schema->string()
                ->description('summary (default), detailed, or emails for a compact de-duplicated address list.'),
            'limit' => $schema->integer()
                ->description('Rows to return, 1-500. Defaults to 50.'),
            'offset' => $schema->integer()
                ->description('Row offset for paging while has_more is true.'),
        ];
    }

    /**
     * Shape the payload for MCP clients.
     *
     * `count` is kept alongside the richer paging keys so existing clients that
     * read `count` from the previous free-text-only tool keep working.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function present(array $result, string $fields): array
    {
        $result = $this->redact($result, $fields);
        $result['count'] = $result['returned'] ?? 0;

        return $result;
    }

    /**
     * Omit LRN, phone, and other direct contact details from MCP output so the
     * structured filters can answer cohort questions without widening the PII
     * surface that external clients already receive.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function redact(array $result, string $fields): array
    {
        $rows = (array) ($result['students'] ?? []);

        $result['students'] = array_map(static function (array $row): array {
            unset($row['lrn'], $row['phone']);

            return $row;
        }, $rows);

        if ($fields === 'emails') {
            $result['emails'] = array_values(array_unique(array_filter(
                (array) ($result['emails'] ?? []),
                static fn ($email): bool => is_string($email) && $email !== '',
            )));
        }

        return $result;
    }
}
