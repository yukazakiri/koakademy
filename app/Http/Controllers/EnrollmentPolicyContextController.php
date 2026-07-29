<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Enrollment\EnrollmentPolicyResolver;
use App\Enrollment\EnrollmentRuleTiming;
use App\Features\DynamicEnrollmentPolicies;
use App\Http\Requests\ShowEnrollmentPolicyContextRequest;
use App\Models\Course;
use App\Models\School;
use App\Services\GeneralSettingsService;
use Illuminate\Http\JsonResponse;
use Laravel\Pennant\Feature;

final readonly class EnrollmentPolicyContextController
{
    public function __construct(
        private EnrollmentPolicyResolver $resolver,
        private EnrollmentPolicyRegistry $registry,
        private GeneralSettingsService $settings,
    ) {}

    public function __invoke(ShowEnrollmentPolicyContextRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $course = Course::query()->findOrFail((int) $validated['course_id']);
        $dynamic = Feature::active(DynamicEnrollmentPolicies::class);

        if (! $dynamic) {
            return response()->json([
                'runtime' => 'legacy',
                'requirements' => $this->legacyRequirements((string) $validated['student_type']),
                'assignment' => ['strategy' => 'legacy'],
                'billing' => ['allowed_payment_methods' => []],
                'eligibility' => ['passed' => true, 'messages' => []],
            ]);
        }

        $context = new EnrollmentContext(
            schoolId: $course->school_id === null ? $this->resolveSiteSchoolId() : (int) $course->school_id,
            studentType: (string) $validated['student_type'],
            courseId: (int) $course->id,
            schoolYear: $this->settings->getCurrentSchoolYearString(),
            semester: $this->settings->getCurrentSemester(),
            yearLevel: isset($validated['academic_year']) ? (int) $validated['academic_year'] : null,
            channel: 'public',
        );
        $compiled = $this->resolver->resolve($context);
        $failures = [];
        foreach ($compiled->configuration['rules'] ?? [] as $rule) {
            if (! EnrollmentRuleTiming::appliesAtEntry((string) $rule['handler'])) {
                continue;
            }

            $result = $this->registry->rule((string) $rule['handler'])
                ->evaluate($context, $rule['configuration'] ?? []);
            if (! $result->passed) {
                $failures[] = $result->message;
            }
        }

        return response()->json([
            'runtime' => 'policy_v1',
            'requirements' => collect($compiled->configuration['requirements'] ?? [])
                ->filter(fn (mixed $requirement): bool => is_array($requirement) && ($requirement['enabled'] ?? true) !== false)
                ->map(fn (array $requirement): array => [
                    'key' => (string) $requirement['key'],
                    'label' => (string) ($requirement['label'] ?? $requirement['key']),
                    'description' => $requirement['description'] ?? null,
                    'required' => (bool) ($requirement['required'] ?? true),
                    'enforcement_step' => $requirement['enforcement_step'] ?? null,
                ])
                ->values()
                ->all(),
            'assignment' => ['strategy' => data_get($compiled->configuration, 'assignment.strategy')],
            'billing' => [
                'allowed_payment_methods' => array_values(data_get($compiled->configuration, 'billing.allowed_payment_methods', [])),
                'receipt_mode' => data_get($compiled->configuration, 'billing.configuration.receipt_mode'),
                'minimum_payment' => data_get($compiled->configuration, 'billing.configuration.minimum_payment'),
            ],
            'eligibility' => ['passed' => $failures === [], 'messages' => $failures],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyRequirements(string $studentType): array
    {
        if ($studentType !== 'tesda') {
            return [];
        }

        return [
            ['key' => 'psa_birth_certificate', 'label' => 'PSA Birth Certificate', 'description' => null, 'required' => true, 'enforcement_step' => null],
            ['key' => 'high_school_diploma', 'label' => 'High School Diploma / Form 137', 'description' => null, 'required' => true, 'enforcement_step' => null],
            ['key' => '2x2_photo', 'label' => '2x2 ID Photo', 'description' => null, 'required' => true, 'enforcement_step' => null],
            ['key' => 'other', 'label' => 'Other Supporting Documents', 'description' => null, 'required' => false, 'enforcement_step' => null],
        ];
    }

    private function resolveSiteSchoolId(): ?int
    {
        $schoolId = School::query()->active()->orderBy('id')->value('id')
            ?? School::query()->orderBy('id')->value('id');

        return $schoolId === null ? null : (int) $schoolId;
    }
}
