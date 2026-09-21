<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Classes;
use App\Models\Course;
use App\Services\CurriculumCapabilityResolver;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List active courses and scheduled classes for the selected school and academic period.')]
#[IsReadOnly]
final class ListAcademicOfferingsTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?CurriculumCapabilityResolver $curriculumCapabilities = null,
        private ?GeneralSettingsService $settings = null,
    ) {
        $this->curriculumCapabilities ??= app(CurriculumCapabilityResolver::class);
        $this->settings ??= app(GeneralSettingsService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'ViewAny:Course', 'You are not permitted to view academic offerings.');
        $this->requirePermission($user, 'ViewAny:Classes', 'You are not permitted to view scheduled classes.');

        $validated = $request->validate([
            'school_year' => ['nullable', 'string', 'regex:/^\d{4}\s?-\s?\d{4}$/'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'school_year.regex' => 'School year must use YYYY - YYYY or YYYY-YYYY format.',
        ]);

        $school = $this->school();
        $schoolYear = GeneralSettingsService::normalizeSchoolYear((string) ($validated['school_year'] ?? $this->settings->getCurrentSchoolYearString()));
        $semester = (int) ($validated['semester'] ?? $this->settings->getCurrentSemester());
        $limit = (int) ($validated['limit'] ?? 25);

        $courses = Course::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->limit($limit)
            ->get(['id', 'code', 'title', 'units', 'year_level', 'semester', 'curriculum_kind'])
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'units' => $course->units,
                'year_level' => $course->year_level,
                'semester' => $course->semester,
                'curriculum_kind' => $this->curriculumCapabilities->kindForCourse($course),
            ])
            ->values()
            ->all();

        $classes = Classes::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->with(['faculty:id,first_name,last_name', 'room:id,name'])
            ->withCount('class_enrollments')
            ->orderBy('subject_code')
            ->orderBy('section')
            ->limit($limit)
            ->get(['id', 'subject_code', 'section', 'school_year', 'semester', 'faculty_id', 'room_id', 'maximum_slots', 'classification'])
            ->map(fn (Classes $class): array => [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'section' => $class->section,
                'school_year' => $class->school_year,
                'semester' => $class->semester,
                'classification' => $class->classification,
                'faculty_name' => $class->faculty?->full_name,
                'room' => $class->room?->name,
                'maximum_slots' => $class->maximum_slots,
                'enrolled_count' => $class->class_enrollments_count,
            ])
            ->values()
            ->all();

        return Response::structured([
            'school' => ['id' => $school->id, 'name' => $school->name],
            'academic_period' => ['school_year' => $schoolYear, 'semester' => $semester],
            'curriculum_capabilities' => $this->curriculumCapabilities->forSchool($school)->values()->all(),
            'courses' => $courses,
            'classes' => $classes,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'school_year' => $schema->string()->description('Optional school year as YYYY - YYYY. Defaults to the current academic period.'),
            'semester' => $schema->integer()->enum([1, 2])->description('Optional semester. Defaults to the current academic period.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum courses and classes to return for each list. Defaults to 25.'),
        ];
    }
}
