<?php

declare(strict_types=1);

use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentPolicyCompiler;
use App\Enrollment\EnrollmentPolicyInheritanceService;
use App\Enrollment\EnrollmentPolicyManager;
use App\Enrollment\EnrollmentPolicyPreset;
use App\Enrollment\EnrollmentPolicyResolver;
use App\Enrollment\EnrollmentPolicyRolloutService;
use App\Enrollment\EnrollmentPolicySimulationService;
use App\Features\DynamicEnrollmentPolicies;
use App\Models\EnrollmentPolicyVersion;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

it('publishes scoped drafts and resolves them after the global layer', function (): void {
    $manager = app(EnrollmentPolicyManager::class);
    $school = School::factory()->create();
    $author = User::factory()->create();
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['rules'][] = [
        'key' => 'school_documents',
        'handler' => 'eligibility.documents',
        'configuration' => ['fact_key' => 'documents_complete'],
    ];

    $policy = $manager->create([
        'name' => 'School-specific enrollment',
        'school_id' => $school->id,
        'configuration' => $configuration,
    ], $author);
    $draft = $policy->versions->first();
    $manager->publish($policy, $draft, $author);

    $compiled = app(EnrollmentPolicyResolver::class)->resolve(new EnrollmentContext(
        schoolId: $school->id,
        studentType: 'college',
        courseId: null,
        schoolYear: '2026 - 2027',
        semester: 1,
    ));

    expect(collect($compiled->configuration['rules'])->pluck('key'))
        ->toContain('school_documents')
        ->and($compiled->sourceVersionIds)->toContain($draft->id);
});

it('reports completion payment rules as deferred instead of enrollment blockers during simulation', function (): void {
    $manager = app(EnrollmentPolicyManager::class);
    $school = School::factory()->create();
    $author = User::factory()->create();
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['rules'][] = [
        'key' => 'minimum_payment',
        'handler' => 'billing.minimum_payment',
        'configuration' => ['type' => 'percentage', 'value' => 20],
    ];
    $policy = $manager->create([
        'name' => 'Deferred payment simulation',
        'school_id' => $school->id,
        'configuration' => $configuration,
    ], $author);
    $manager->publish($policy, $policy->versions->first(), $author);

    $result = app(EnrollmentPolicySimulationService::class)->simulate(new EnrollmentContext(
        schoolId: $school->id,
        studentType: 'college',
        courseId: null,
        schoolYear: '2035 - 2036',
        semester: 1,
    ));
    $minimumPayment = collect($result['eligibility'])->firstWhere('handler', 'billing.minimum_payment');

    expect($minimumPayment['passed'])->toBeTrue()
        ->and($minimumPayment['metadata']['deferred'])->toBeTrue()
        ->and(collect($result['blockers'])->pluck('handler'))->not->toContain('billing.minimum_payment');
});

it('previews connected routes from the entry instead of displaying every stage as one linear journey', function (): void {
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['transitions'] = [
        ['key' => 'public_only', 'label' => 'Public', 'to' => 'completed', 'fallback' => false,
            'conditions' => [['handler' => 'availability.channel', 'configuration' => ['allowed' => ['public']]]]],
        ...$configuration['workflow']['steps'][0]['transitions'],
    ];
    $compiled = app(EnrollmentPolicyCompiler::class)->compile([['version_id' => 0, 'configuration' => $configuration]]);
    $simulation = app(EnrollmentPolicySimulationService::class)->simulateCompiled($compiled, new EnrollmentContext(
        schoolId: null, studentType: null, courseId: null, schoolYear: null, semester: null, channel: 'public',
    ));

    expect(array_column($simulation['workflow_route'], 'key'))->toBe(['submitted', 'completed'])
        ->and($simulation['actions_executed'])->toBeFalse()
        ->and($simulation['writes_performed'])->toBeFalse();
});

it('stores sparse scoped drafts and reports inherited source provenance', function (): void {
    $manager = app(EnrollmentPolicyManager::class);
    $school = School::factory()->create();
    $author = User::factory()->create();

    $policy = $manager->create([
        'name' => 'Sparse school changes',
        'school_id' => $school->id,
        'inherit' => true,
    ], $author);
    $draft = $policy->versions->first();

    expect($draft->configuration)->toBe(['schema_version' => 1]);

    $inheritance = app(EnrollmentPolicyInheritanceService::class)->describe($policy);
    expect($inheritance['configuration'])->toHaveKeys(['workflow', 'assignment', 'billing'])
        ->and($inheritance['layers'])->not->toBeEmpty()
        ->and($inheritance['source_map']['workflow']['version_id'])->toBeInt();

    $saved = $manager->saveDraft($policy, [
        'schema_version' => 1,
        'rules' => [[
            'key' => 'channels',
            'handler' => 'availability.channel',
            'configuration' => ['allowed' => ['administrator']],
        ]],
    ], 'Restrict this school to assisted enrollment.', $author);

    expect($saved->configuration)->toHaveCount(2)
        ->and($saved->configuration['rules'][0]['configuration']['allowed'])->toBe(['administrator']);
});

it('uses the same deterministic workflow permission candidate for simulation and publication', function (): void {
    $manager = app(EnrollmentPolicyManager::class);
    $author = User::factory()->create();
    $school = School::factory()->create();
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['authorized_role_ids'] = [];

    $policy = $manager->create([
        'name' => 'Deterministic permission policy',
        'school_id' => $school->id,
        'configuration' => $configuration,
    ], $author);
    $draft = $policy->versions->first();
    $candidate = $manager->publicationCandidate($policy, $draft, $draft->configuration);
    $expectedPermission = "EnrollmentPolicy:{$policy->id}:Version:1:Step:submitted";

    expect($candidate['workflow']['steps'][0])
        ->not->toHaveKey('authorized_role_ids')
        ->and($candidate['workflow']['steps'][0]['permission'])->toBe($expectedPermission);

    $published = $manager->publish($policy, $draft, $author);
    expect($published->configuration['workflow']['steps'][0])
        ->not->toHaveKey('authorized_role_ids')
        ->and($published->configuration['workflow']['steps'][0]['permission'])->toBe($expectedPermission);
});

it('rejects duplicate nullable scope combinations using the scope fingerprint', function (): void {
    $manager = app(EnrollmentPolicyManager::class);
    $author = User::factory()->create();

    expect(fn () => $manager->create(['name' => 'Another global policy'], $author))
        ->toThrow(ValidationException::class, 'exact scope');
});

it('rejects ambiguous workflow action or route keys before publication', function (string $collection): void {
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0][$collection][] = $configuration['workflow']['steps'][0][$collection][0];

    $author = User::factory()->create();
    expect(fn () => app(EnrollmentPolicyManager::class)->create([
        'name' => 'Ambiguous identifiers', 'school_id' => School::factory()->create()->id, 'configuration' => $configuration,
    ], $author))
        ->toThrow(ValidationException::class, 'keys must be present and unique');
})->with(['actions', 'transitions']);

it('keeps historical definitions with positional action payloads readable while requiring keys on new edits', function (): void {
    $configuration = EnrollmentPolicyPreset::standard();
    unset($configuration['workflow']['steps'][0]['actions'][0]['key']);
    $compiler = app(EnrollmentPolicyCompiler::class);

    expect($compiler->compile([['version_id' => 1, 'configuration' => $configuration]])->checksum)->toHaveLength(64);
    expect(fn () => $compiler->validateAuthoringConfiguration($configuration))
        ->toThrow(ValidationException::class, 'keys must be present and unique');
});

it('rejects a terminal outcome that the runtime cannot report', function (): void {
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][2]['outcome'] = 'unknown';

    expect(fn () => app(EnrollmentPolicyManager::class)->create([
        'name' => 'Unsupported outcome', 'school_id' => School::factory()->create()->id, 'configuration' => $configuration,
    ], User::factory()->create()))->toThrow(ValidationException::class, 'supported outcome');
});

it('uses period then program then student type then school for equal-specificity overrides', function (): void {
    $manager = app(EnrollmentPolicyManager::class);
    $author = User::factory()->create();
    $school = School::factory()->create();

    $schoolConfiguration = EnrollmentPolicyPreset::standard();
    $schoolConfiguration['rules'][] = ['key' => 'priority', 'handler' => 'eligibility.student_type', 'configuration' => ['allowed' => ['college']]];
    $schoolPolicy = $manager->create(['name' => 'School override', 'school_id' => $school->id, 'configuration' => $schoolConfiguration], $author);
    $manager->publish($schoolPolicy, $schoolPolicy->versions->first(), $author);

    $periodConfiguration = EnrollmentPolicyPreset::standard();
    $periodConfiguration['rules'][] = ['key' => 'priority', 'handler' => 'eligibility.student_type', 'configuration' => ['allowed' => ['tesda']]];
    $periodPolicy = $manager->create(['name' => 'Period override', 'school_year' => '2032 - 2033', 'configuration' => $periodConfiguration], $author);
    $manager->publish($periodPolicy, $periodPolicy->versions->first(), $author);

    $resolved = app(EnrollmentPolicyResolver::class)->resolve(new EnrollmentContext(
        schoolId: $school->id,
        studentType: 'college',
        courseId: null,
        schoolYear: '2032 - 2033',
        semester: 1,
    ));

    expect(collect($resolved->configuration['rules'])->firstWhere('key', 'priority')['configuration']['allowed'])
        ->toBe(['tesda']);
});

it('keeps published versions immutable', function (): void {
    $version = EnrollmentPolicyVersion::query()->published()->firstOrFail();

    expect(fn () => $version->update(['change_notes' => 'mutated']))
        ->toThrow(LogicException::class, 'immutable');
});

it('changes only the runtime of future enrollments when rollout is toggled', function (): void {
    $legacy = StudentEnrollment::factory()->create();
    expect($legacy->workflow_runtime)->toBe(StudentEnrollment::WorkflowRuntimeLegacy);

    $rollout = app(EnrollmentPolicyRolloutService::class);
    $rollout->activate();
    expect(Feature::active(DynamicEnrollmentPolicies::class))->toBeTrue();

    $policyEnrollment = StudentEnrollment::factory()->create([
        'school_year' => '2030 - 2031',
        'semester' => 1,
    ]);
    expect($policyEnrollment->workflow_runtime)->toBe(StudentEnrollment::WorkflowRuntimePolicyV1)
        ->and($policyEnrollment->enrollment_policy_snapshot_id)->not->toBeNull();

    $rollout->deactivate();
    $futureLegacy = StudentEnrollment::factory()->create([
        'school_year' => '2031 - 2032',
        'semester' => 1,
    ]);

    expect($futureLegacy->workflow_runtime)->toBe(StudentEnrollment::WorkflowRuntimeLegacy)
        ->and($policyEnrollment->refresh()->workflow_runtime)->toBe(StudentEnrollment::WorkflowRuntimePolicyV1)
        ->and($legacy->refresh()->workflow_runtime)->toBe(StudentEnrollment::WorkflowRuntimeLegacy);
});

it('creates an unpublished compatibility draft without changing activation state', function (): void {
    $policy = App\Models\EnrollmentPolicy::query()
        ->where('name', 'Global enrollment policy (migrated)')
        ->firstOrFail();
    $activeVersionId = $policy->active_version_id;
    $draft = $policy->versions()
        ->where('change_notes', 'Compatibility draft: explicit database-driven enrollment runtime actions.')
        ->sole();

    expect($draft->state)->toBe(EnrollmentPolicyVersion::Draft)
        ->and($draft->id)->not->toBe($activeVersionId)
        ->and($policy->refresh()->active_version_id)->toBe($activeVersionId)
        ->and(data_get($draft->configuration, 'workflow.steps.0.actions.0.handler'))->toBe('enrollment.assign_subjects')
        ->and(data_get($draft->configuration, 'billing.configuration.discount_scope'))->toBe('lecture_only')
        ->and(Feature::active(DynamicEnrollmentPolicies::class))->toBeFalse();
});

it('applies rollout to previously unseen staff and public submissions, then disables future scopes', function (): void {
    $rollout = app(EnrollmentPolicyRolloutService::class);
    $this->actingAs(User::factory()->create());
    $rollout->activate();
    $newActor = User::factory()->create();

    expect(Feature::for($newActor)->active(DynamicEnrollmentPolicies::class))->toBeTrue()
        ->and(Feature::for(null)->active(DynamicEnrollmentPolicies::class))->toBeTrue();

    $rollout->deactivate();
    expect(Feature::for($newActor)->active(DynamicEnrollmentPolicies::class))->toBeFalse()
        ->and(Feature::for(User::factory()->create())->active(DynamicEnrollmentPolicies::class))->toBeFalse()
        ->and(Feature::for(null)->active(DynamicEnrollmentPolicies::class))->toBeFalse();
});
