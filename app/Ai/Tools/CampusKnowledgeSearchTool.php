<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\GradingPolicy;
use App\Services\GeneralSettingsService;
use App\Services\GradingSystemService;
use App\Services\TenantContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class CampusKnowledgeSearchTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Look up recorded academic period and grading configuration facts for the current school. General student handbook, attendance, and clearance prose are not stored in a verified institutional knowledge base and are reported as unavailable.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
        ]);

        $school = app(TenantContext::class)->getCurrentSchool();
        $settingsService = app(GeneralSettingsService::class);
        $schoolYear = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getAvailableSemesters()[$settingsService->getCurrentSemester()] ?? (string) $settingsService->getCurrentSemester();

        $queryLower = mb_strtolower($validated['query']);
        $wantsGrading = str_contains($queryLower, 'grad') || str_contains($queryLower, 'gwa') || str_contains($queryLower, 'pass');
        $wantsEnrollment = str_contains($queryLower, 'enroll') || str_contains($queryLower, 'semester') || str_contains($queryLower, 'term') || str_contains($queryLower, 'year');

        $gradingFacts = null;
        if ($wantsGrading && $school !== null && GradingPolicy::query()->where('school_id', $school->id)->exists()) {
            $gradingConfig = app(GradingSystemService::class)->getConfig($school);
            $gradingFacts = [
                'name' => $gradingConfig['name'] ?? null,
                'input_type' => $gradingConfig['input_type'] ?? null,
                'direction' => $gradingConfig['direction'] ?? null,
                'gwa_formula' => $gradingConfig['gwa_formula'] ?? null,
                'bands' => $gradingConfig['bands'] ?? [],
                'components' => $gradingConfig['components'] ?? [],
            ];
        }

        $unavailableTopics = [];
        foreach (['attendance', 'clearance', 'handbook', 'discipline', 'refund'] as $topic) {
            if (str_contains($queryLower, $topic)) {
                $unavailableTopics[] = $topic;
            }
        }

        return (string) json_encode([
            'query' => $validated['query'],
            'status' => ($unavailableTopics === [] && ($wantsEnrollment || $gradingFacts !== null)) ? 'partial' : 'unavailable',
            'available' => $unavailableTopics === [] && ($wantsEnrollment || $gradingFacts !== null),
            'reason' => 'handbook_corpus_not_configured',
            'message' => 'No general student handbook, attendance rulebook, or clearance policy corpus is stored in KoAkademy. Only the recorded academic period and configured school grading policy (when present) are returned.',
            'academic_period' => [
                'school_year' => $schoolYear,
                'semester' => $semester,
            ],
            'grading_policy' => $gradingFacts,
            'unavailable_topics' => $unavailableTopics,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }
}
