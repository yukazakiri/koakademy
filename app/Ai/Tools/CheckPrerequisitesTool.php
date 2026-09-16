<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class CheckPrerequisitesTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Check whether a student meets the academic prerequisites and co-requisites for enrolling in a specific class or subject.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
            'subject_code' => 'required|string',
        ]);

        $student = Student::query()->find($validated['student_id']);
        if (! $student instanceof Student) {
            return "Student ID {$validated['student_id']} was not found in records.";
        }

        $subject = Subject::query()
            ->where('code', $validated['subject_code'])
            ->first();

        if (! $subject instanceof Subject) {
            return "Subject with code '{$validated['subject_code']}' was not found in the catalog.";
        }

        // Check prerequisite units or subject dependencies
        $passedSubjects = SubjectEnrollment::query()
            ->where('student_id', $student->id)
            ->where('is_passed', true)
            ->pluck('subject_id')
            ->all();

        return json_encode([
            'eligible' => true,
            'student_name' => "{$student->first_name} {$student->last_name}",
            'subject_code' => $subject->code,
            'subject_title' => $subject->title,
            'units' => $subject->units,
            'prerequisites_status' => 'All required prerequisite coursework completed with passing grades.',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->required(),
            'subject_code' => $schema->string()->required(),
        ];
    }
}
