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

#[Description('Search students in the selected school by name, student number, or email. Supports single search terms, formatted names ("LAST, FIRST M."), or batches/lists of student names in a single call.')]
#[IsReadOnly]
final class SearchStudentsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'ViewAny:Student', 'You are not permitted to search student records.');

        $rawQuery = $request->get('query');
        $queries = $request->get('queries') ?? $request->get('names');

        // Auto-detect multiline queries
        if (is_string($rawQuery) && (str_contains($rawQuery, "\n") || str_contains($rawQuery, "\r"))) {
            $split = array_values(array_filter(
                array_map('trim', preg_split('/[\r\n]+/', $rawQuery) ?: []),
                static function (string $line): bool {
                    $clean = mb_trim(preg_replace('/^\d+[\s\.\)\-]+\s*/u', '', $line));
                    $upper = mb_strtoupper($clean);

                    return filled($clean)
                        && ! str_starts_with($upper, 'BACHELOR')
                        && ! str_starts_with($upper, 'LIST')
                        && ! str_starts_with($upper, 'BATCH');
                }
            ));

            if (count($split) > 1) {
                $queries = $split;
            }
        }

        // Batch search mode
        if (is_array($queries) && ! empty($queries)) {
            $results = [];
            $foundCount = 0;

            foreach (array_slice($queries, 0, 150) as $rawItem) {
                if (! is_string($rawItem) || blank($rawItem)) {
                    continue;
                }

                $clean = mb_trim(preg_replace('/^\d+[\s\.\)\-]+\s*/u', '', $rawItem));
                if (blank($clean)) {
                    continue;
                }

                $student = $this->resolveStudent($clean);

                if ($student instanceof Student) {
                    $foundCount++;
                    $results[] = [
                        'query' => $rawItem,
                        'found' => true,
                        'student_id' => (string) $student->student_id,
                        'name' => $student->full_name,
                        'email' => $student->email,
                        'course' => $student->Course?->code ?? $student->Course?->title ?? 'N/A',
                        'status' => $student->status instanceof BackedEnum ? $student->status->value : (string) $student->status,
                    ];
                } else {
                    $results[] = [
                        'query' => $rawItem,
                        'found' => false,
                        'name' => null,
                        'email' => null,
                    ];
                }
            }

            return Response::structured([
                'count' => count($results),
                'found_count' => $foundCount,
                'students' => $results,
            ]);
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'query.required' => 'Provide a student name, number, or email to search.',
        ]);

        $query = mb_trim((string) $validated['query']);
        $limit = (int) ($validated['limit'] ?? 10);

        $students = Student::query()
            ->select(['id', 'student_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'status', 'course_id'])
            ->with('Course:id,code,title')
            ->where(function (Builder $builder) use ($query): void {
                $term = '%'.mb_strtolower(addcslashes($query, '%_\\')).'%';

                $builder->whereRaw('CAST(student_id AS CHAR) LIKE ?', ['%'.$query.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(TRIM(CONCAT_WS(' ', first_name, middle_name, last_name, suffix))) LIKE ?", [$term]);

                if (str_contains($query, ',')) {
                    [$last, $first] = explode(',', $query, 2);
                    $last = mb_trim($last);
                    $first = mb_trim($first);
                    $firstOnly = mb_trim(preg_replace('/\s+[A-Za-z]\.?$/u', '', $first) ?? $first);
                    $firstWord = explode(' ', $firstOnly)[0] ?? '';

                    $builder->orWhere(function (Builder $sub) use ($last, $firstWord): void {
                        $sub->whereRaw('LOWER(last_name) LIKE ?', ['%'.mb_strtolower($last).'%'])
                            ->whereRaw('LOWER(first_name) LIKE ?', ['%'.mb_strtolower($firstWord).'%']);
                    });
                }
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
            ]);

        return Response::structured([
            'query' => $query,
            'count' => $students->count(),
            'students' => $students->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(2)->max(500)->description('Name, student number, email, or a multiline text block of student names.'),
            'queries' => $schema->array()->description('Batch array of student names to search.')->items($schema->string()),
            'names' => $schema->array()->description('Alias for queries: array of student names.')->items($schema->string()),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum number of results to return (default: 10).'),
        ];
    }

    private function resolveStudent(string $identifier): ?Student
    {
        $school = $this->school();

        $base = Student::query()
            ->with(['Course'])
            ->where(fn ($q) => $q->where('school_id', $school->id)->orWhere('institution_id', $school->id));

        if (str_contains($identifier, '@')) {
            return (clone $base)->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->first();
        }

        if (is_numeric($identifier)) {
            return (clone $base)->where(function ($q) use ($identifier) {
                $q->where('student_id', (int) $identifier)
                    ->orWhere('id', (int) $identifier)
                    ->orWhere('lrn', $identifier);
            })->first();
        }

        if (str_contains($identifier, ',')) {
            [$last, $rest] = explode(',', $identifier, 2);
            $last = mb_trim($last);
            $rest = mb_trim($rest);
            $firstOnly = mb_trim(preg_replace('/\s+[A-Za-z]\.?$/u', '', $rest) ?? $rest);
            $firstWord = explode(' ', $firstOnly)[0] ?? '';

            $match = (clone $base)
                ->whereRaw('LOWER(last_name) LIKE ?', ['%'.mb_strtolower($last).'%'])
                ->where(function (Builder $builder) use ($firstOnly, $firstWord): void {
                    $builder->whereRaw('LOWER(first_name) LIKE ?', ['%'.mb_strtolower($firstOnly).'%'])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.mb_strtolower($firstWord).'%']);
                })
                ->first();

            if ($match instanceof Student) {
                return $match;
            }

            return (clone $base)
                ->whereRaw('LOWER(last_name) LIKE ?', ['%'.mb_strtolower($last).'%'])
                ->first();
        }

        $words = preg_split('/\s+/u', $identifier) ?: [];
        if (count($words) === 1) {
            $term = mb_strtolower($words[0]);

            return (clone $base)
                ->where(function (Builder $builder) use ($term): void {
                    $builder->whereRaw('LOWER(last_name) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$term.'%']);
                })
                ->first();
        }

        $w1 = mb_strtolower($words[0]);
        $w2 = mb_strtolower($words[count($words) - 1]);

        return (clone $base)
            ->where(function (Builder $builder) use ($w1, $w2): void {
                $builder->where(function (Builder $sub) use ($w1, $w2): void {
                    $sub->whereRaw('LOWER(first_name) LIKE ?', ['%'.$w1.'%'])
                        ->whereRaw('LOWER(last_name) LIKE ?', ['%'.$w2.'%']);
                })->orWhere(function (Builder $sub) use ($w1, $w2): void {
                    $sub->whereRaw('LOWER(first_name) LIKE ?', ['%'.$w2.'%'])
                        ->whereRaw('LOWER(last_name) LIKE ?', ['%'.$w1.'%']);
                });
            })
            ->first();
    }
}
