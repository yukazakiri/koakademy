<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Course;
use App\Models\Subject;
use App\Services\CurriculumCapabilityResolver;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the complete curriculum of an academic program or course broken down by year level and semester with lecture/lab units, prerequisite codes, and credit types.')]
#[IsReadOnly]
final class GetCourseCurriculumTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?CurriculumCapabilityResolver $resolver = null)
    {
        $this->resolver ??= app(CurriculumCapabilityResolver::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);

        if (! $user->isStudentRole()) {
            $this->requirePermission($user, 'View:Course', 'You are not permitted to view course curriculum details.');
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'min:1'],
            'year_level' => ['nullable', 'integer', 'between:1,6'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $school = $this->school();
        $course = Course::query()->findOrFail($validated['course_id']);

        if (! $course->belongsToSchool($school) && (int) $course->school_id !== (int) $school->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The course does not belong to the selected school.');
        }

        $subjectsQuery = Subject::query()
            ->where('course_id', $course->id)
            ->when(isset($validated['year_level']), fn ($q) => $q->where('academic_year', (int) $validated['year_level']))
            ->when(isset($validated['semester']), fn ($q) => $q->where('semester', (int) $validated['semester']))
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->orderBy('code');

        $subjects = $subjectsQuery->get()->map(fn (Subject $subject): array => [
            'id' => $subject->id,
            'code' => $subject->code,
            'title' => $subject->title,
            'units' => $subject->units,
            'lecture_hours' => $subject->lecture,
            'laboratory_hours' => $subject->laboratory,
            'academic_year' => $subject->academic_year,
            'semester' => $subject->semester,
            'prerequisites' => is_array($subject->pre_riquisite) ? $subject->pre_riquisite : [],
            'classification' => $subject->classification instanceof BackedEnum ? $subject->classification->value : $subject->classification,
            'is_credited' => (bool) $subject->is_credited,
        ])->values()->all();

        return Response::structured([
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'department' => $course->department?->name,
                'curriculum_kind' => $this->resolver->kindForCourse($course),
                'total_units' => $course->units,
            ],
            'filters' => [
                'year_level' => $validated['year_level'] ?? null,
                'semester' => $validated['semester'] ?? null,
            ],
            'subjects_count' => count($subjects),
            'subjects' => $subjects,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'course_id' => $schema->integer()->min(1)->required()->description('The course / program ID.'),
            'year_level' => $schema->integer()->min(1)->max(6)->description('Optional filter by academic year level (e.g. 1, 2, 3, 4).'),
            'semester' => $schema->integer()->enum([1, 2])->description('Optional filter by semester (1 or 2).'),
        ];
    }
}
