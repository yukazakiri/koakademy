<?php

declare(strict_types=1);

use App\Enrollment\EnrollmentPolicyManager;
use App\Enrollment\EnrollmentPolicyPreset;
use App\Features\DynamicEnrollmentPolicies;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use Laravel\Pennant\Feature;

it('returns only the safe server-resolved enrollment requirements for the public form', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2035-08-01',
        'school_ending_date' => '2036-05-31',
        'semester' => 1,
    ]);
    $school = School::factory()->create();
    $course = Course::factory()->create(['school_id' => $school->id]);
    $query = [
        'student_type' => 'college',
        'course_id' => $course->id,
        'academic_year' => 1,
    ];

    $this->getJson(route('enrollment.policy-context.show', $query))
        ->assertOk()
        ->assertJsonPath('runtime', 'legacy')
        ->assertJsonMissingPath('configuration');

    $author = User::factory()->create();
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['requirements'] = [[
        'key' => 'form_138',
        'label' => 'Form 138',
        'description' => 'Upload a clear copy of every page.',
        'required' => true,
        'enforcement_step' => 'academic_verified',
    ]];
    $configuration['rules'][] = [
        'key' => 'minimum_payment',
        'handler' => 'billing.minimum_payment',
        'configuration' => ['type' => 'fixed', 'value' => 500],
    ];
    $manager = app(EnrollmentPolicyManager::class);
    $policy = $manager->create([
        'name' => 'Public requirements',
        'school_id' => $school->id,
        'configuration' => $configuration,
    ], $author);
    $manager->publish($policy, $policy->versions->first(), $author);
    Feature::activate(DynamicEnrollmentPolicies::class);

    $this->getJson(route('enrollment.policy-context.show', $query))
        ->assertOk()
        ->assertJsonPath('runtime', 'policy_v1')
        ->assertJsonPath('requirements.0.key', 'form_138')
        ->assertJsonPath('requirements.0.enforcement_step', 'academic_verified')
        ->assertJsonPath('eligibility.passed', true)
        ->assertJsonMissingPath('configuration')
        ->assertJsonMissingPath('rules')
        ->assertJsonMissingPath('workflow');
});
