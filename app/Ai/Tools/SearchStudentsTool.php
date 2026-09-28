<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class SearchStudentsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search student directory records by name, student number, or email. Supports single queries, formatted names ("LAST, FIRST M."), or batches/lists of student names to retrieve student IDs, emails, programs, and statuses in one call.';
    }

    public function handle(Request $request): Stringable|string
    {
        $rawQuery = $request['query'] ?? null;
        $queries = $request['queries'] ?? ($request['names'] ?? null);

        // Auto-detect multiline text blocks containing multiple student names
        if (is_string($rawQuery) && (str_contains($rawQuery, "\n") || str_contains($rawQuery, "\r"))) {
            $split = array_values(array_filter(
                array_map('trim', preg_split('/[\r\n]+/', $rawQuery) ?: []),
                static function (string $line): bool {
                    $clean = mb_trim(preg_replace('/^\d+[\s\.\)\-]+\s*/u', '', $line));
                    $upper = mb_strtoupper($clean);

                    return filled($clean)
                        && ! str_starts_with($upper, 'BACHELOR')
                        && ! str_starts_with($upper, 'LIST')
                        && ! str_starts_with($upper, 'BATCH')
                        && ! str_starts_with($upper, 'NAME')
                        && ! str_starts_with($upper, 'STUDENT');
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
                $upper = mb_strtoupper($clean);
                if (blank($clean) || str_starts_with($upper, 'BACHELOR') || str_starts_with($upper, 'LIST') || str_starts_with($upper, 'BATCH')) {
                    continue;
                }

                $student = $this->resolveSingleStudent($clean);

                if ($student instanceof Student) {
                    $foundCount++;
                    $results[] = [
                        'query' => $rawItem,
                        'found' => true,
                        'student_id' => (string) $student->student_id,
                        'name' => mb_trim("{$student->first_name} {$student->last_name}"),
                        'email' => $student->email,
                        'course' => $student->course?->title ?? $student->course?->code ?? 'N/A',
                        'year_level' => $student->academic_year,
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

            return json_encode([
                'count' => count($results),
                'found_count' => $foundCount,
                'students' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // Single query mode
        $term = mb_trim(preg_replace('/^\d+[\s\.\)\-]+\s*/u', '', (string) ($rawQuery ?? '')));
        $limit = min(50, max(1, (int) ($request['limit'] ?? 10)));

        if (blank($term)) {
            return json_encode([
                'count' => 0,
                'students' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $students = $this->searchStudents($term, $limit);

        return json_encode([
            'count' => $students->count(),
            'students' => $students->map(static function (Student $student): array {
                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => mb_trim("{$student->first_name} {$student->last_name}"),
                    'email' => $student->email,
                    'course' => $student->course?->title ?? $student->course?->code ?? 'N/A',
                    'year_level' => $student->academic_year,
                    'status' => $student->status instanceof BackedEnum ? $student->status->value : (string) $student->status,
                    'enrolled_classes_count' => $student->classEnrollments?->count() ?? 0,
                ];
            })->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Student name, student ID number, or email. Can also be a multiline block of student names.'),
            'queries' => $schema->array()->description('Array of student names or identifiers to search in batch.')->items($schema->string()),
            'names' => $schema->array()->description('Alias for queries. List of student names to look up.')->items($schema->string()),
            'limit' => $schema->integer()->description('Maximum number of results to return for single query (default 10).'),
        ];
    }

    private function resolveSingleStudent(string $query): ?Student
    {
        $clean = mb_trim($query);

        if (str_contains($clean, '@')) {
            return Student::query()
                ->with(['course'])
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($clean)])
                ->first();
        }

        if (is_numeric($clean)) {
            return Student::query()
                ->with(['course'])
                ->where('student_id', $clean)
                ->orWhere('lrn', $clean)
                ->orWhere('id', (int) $clean)
                ->first();
        }

        if (str_contains($clean, ',')) {
            [$last, $rest] = explode(',', $clean, 2);
            $last = mb_trim($last);
            $rest = mb_trim($rest);
            $firstOnly = mb_trim(preg_replace('/\s+[A-Za-z]\.?$/u', '', $rest) ?? $rest);
            $firstWord = explode(' ', $firstOnly)[0] ?? '';

            $match = Student::query()
                ->with(['course'])
                ->whereRaw('LOWER(last_name) LIKE ?', ['%'.mb_strtolower($last).'%'])
                ->where(function (Builder $builder) use ($firstOnly, $firstWord): void {
                    $builder->whereRaw('LOWER(first_name) LIKE ?', ['%'.mb_strtolower($firstOnly).'%'])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.mb_strtolower($firstWord).'%']);
                })
                ->first();

            if ($match instanceof Student) {
                return $match;
            }

            return Student::query()
                ->with(['course'])
                ->whereRaw('LOWER(last_name) LIKE ?', ['%'.mb_strtolower($last).'%'])
                ->first();
        }

        $words = preg_split('/\s+/u', $clean) ?: [];
        if (count($words) === 1) {
            $term = mb_strtolower($words[0]);

            return Student::query()
                ->with(['course'])
                ->where(function (Builder $builder) use ($term): void {
                    $builder->whereRaw('LOWER(last_name) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$term.'%']);
                })
                ->first();
        }

        $w1 = mb_strtolower($words[0]);
        $w2 = mb_strtolower($words[count($words) - 1]);

        return Student::query()
            ->with(['course'])
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Student>
     */
    private function searchStudents(string $query, int $limit)
    {
        $clean = mb_trim($query);

        return Student::query()
            ->with(['course', 'classEnrollments'])
            ->where(function (Builder $builder) use ($clean): void {
                $term = '%'.mb_strtolower($clean).'%';

                $builder->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('CAST(student_id AS CHAR) LIKE ?', ['%'.$clean.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);

                if (str_contains($clean, ',')) {
                    [$last, $first] = explode(',', $clean, 2);
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
            ->limit($limit)
            ->get();
    }
}
