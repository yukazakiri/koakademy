<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class AuditGraduationClearanceTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Perform a comprehensive audit of a student academic and non-academic clearance checkpoints across Library, Laboratory, Accounting, and Registrar offices.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        
        if (! $user || (! $user->hasRole('super_admin') && ! $user->can('manage_clearance'))) {
            return json_encode([
                'error' => true,
                'message' => 'Unauthorized: This tool requires the manage_clearance permission.',
            ], JSON_PRETTY_PRINT);
        }

        $validated = $request->validate([
            'student_id' => 'required|integer',
        ]);

        $student = Student::query()
            ->with(['clearances'])
            ->find($validated['student_id']);

        if (! $student instanceof Student) {
            return "Student ID {$validated['student_id']} not found.";
        }

        $clearances = $student->clearances;
        $unresolvedClearances = $clearances->where('is_cleared', false);

        return json_encode([
            'student_id' => $student->id,
            'student_name' => "{$student->first_name} {$student->last_name}",
            'all_cleared' => $unresolvedClearances->isEmpty(),
            'total_clearances_recorded' => $clearances->count(),
            'unresolved_count' => $unresolvedClearances->count(),
            'unresolved_items' => $unresolvedClearances->map(fn ($c) => [
                'semester' => $c->formatted_semester,
                'academic_year' => $c->academic_year,
                'remarks' => $c->remarks ?? 'Pending department sign-off',
            ])->values(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->required(),
        ];
    }
}
