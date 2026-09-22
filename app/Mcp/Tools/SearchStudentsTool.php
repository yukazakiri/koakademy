<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Student;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search students in the selected school by name, student number, or email. Results are limited and omit sensitive personal fields.')]
#[IsReadOnly]
final class SearchStudentsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'ViewAny:Student', 'You are not permitted to search student records.');

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ], [
            'query.required' => 'Provide at least two characters of a student name, number, or email.',
        ]);

        $query = mb_trim((string) $validated['query']);
        $limit = (int) ($validated['limit'] ?? 10);

        $students = Student::query()
            ->select(['id', 'student_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'status', 'course_id'])
            ->with('Course:id,code,title')
            ->where(function (Builder $builder) use ($query): void {
                $term = '%'.addcslashes($query, '%_\\').'%';

                $builder->where('student_id', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name, suffix)) LIKE ?", [$term]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status instanceof BackedEnum ? $student->status->value : $student->status,
                'course' => $student->Course === null ? null : [
                    'id' => $student->Course->id,
                    'code' => $student->Course->code,
                    'title' => $student->Course->title,
                ],
            ])
            ->values()
            ->all();

        return Response::structured([
            'query' => $query,
            'count' => count($students),
            'students' => $students,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(2)->max(100)->required()->description('At least two characters from a student name, student number, or email.'),
            'limit' => $schema->integer()->min(1)->max(25)->description('Maximum number of matches to return. Defaults to 10.'),
        ];
    }
}
