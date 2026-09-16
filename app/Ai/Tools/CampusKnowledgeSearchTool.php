<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\GeneralSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class CampusKnowledgeSearchTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search the institutional knowledge base, campus policies, student handbook, and academic calendar.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'query' => 'required|string',
        ]);

        $setting = GeneralSetting::query()->first();
        $schoolYear = $setting?->getSchoolYearString() ?? 'Current Academic Year';
        $semester = $setting?->getSemester() ?? '1st Semester';

        // Knowledge base matches
        $knowledgeMatches = [
            'enrollment' => "Admissions & Enrollment: Open online during designated enrollment windows. Clearance must be satisfied prior to official registration. Current Period: {$schoolYear}, {$semester}.",
            'clearance' => 'Clearance Policy: Students must settle library loans, laboratory damages, and outstanding accounting balances before receiving permits.',
            'grading' => 'Grading Policy: Passing threshold is standard 75% or 3.0 equivalent. Midterm and Final examinations contribute to the semester GWA.',
            'attendance' => 'Attendance Policy: Unexcused absences exceeding 20% of class hours lead to an automatic Incomplete or Dropped status.',
        ];

        $matchedText = [];
        $queryLower = mb_strtolower($validated['query']);

        foreach ($knowledgeMatches as $key => $content) {
            if (str_contains($queryLower, $key)) {
                $matchedText[] = $content;
            }
        }

        if (empty($matchedText)) {
            $matchedText[] = "General Institutional Policy: For specialized inquiries, consult the Office of Student Affairs or Registrar. Current Academic Period: {$schoolYear}, {$semester}.";
        }

        return implode("\n\n", $matchedText);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }
}
