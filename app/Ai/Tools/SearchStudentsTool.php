<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class SearchStudentsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search student directory records by student name, student ID number, program/course, or year level.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'query' => 'required|string',
            'limit' => 'nullable|integer',
        ]);

        $limit = min(50, max(1, $validated['limit'] ?? 10));
        $term = $validated['query'];

        $students = Student::query()
            ->with(['course', 'classEnrollments'])
            ->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('student_id', 'like', "%{$term}%")
                    ->orWhere('lrn', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => mb_trim("{$student->first_name} {$student->last_name}"),
                    'email' => $student->email,
                    'course' => $student->course?->title ?? $student->course?->code ?? 'N/A',
                    'year_level' => $student->academic_year,
                    'status' => $student->status,
                    'enrolled_classes_count' => $student->classEnrollments->count(),
                ];
            })
            ->values()
            ->all();

        return json_encode([
            'count' => count($students),
            'students' => $students,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Student name, student number, or email to search for')->required(),
            'limit' => $schema->integer()->description('Max results to return (default 10, max 50)'),
        ];
    }
}
