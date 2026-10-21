<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\StableRollout;
use App\Features\Toggles\StudentClasses;
use App\Features\Toggles\StudentGradesPreview;
use App\Models\School;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\FeatureManagementService;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    $this->withoutVite();
    DB::table('feature_definitions')->update(['mode' => 'enforced']);
    app(FeatureManagementService::class)->flush();
});

it('joins and leaves beta access for the authenticated user only without changing legacy results', function (): void {
    $this->freezeTime();
    $user = User::factory()->create(['role' => UserRole::Student]);
    $other = User::factory()->create(['role' => UserRole::Student]);
    Feature::for($user)->deactivate(StudentGradesPreview::class);
    $legacyBefore = DB::table('features')->orderBy('id')->get()->toJson();

    $this->actingAs($user)->put(route('profile.beta-access.update'), ['enabled' => true, 'user_id' => $other->id])->assertRedirect();
    expect($user->fresh()->beta_access_joined_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($other->fresh()->beta_access_joined_at)->toBeNull();
    $this->put(route('profile.beta-access.update'), ['enabled' => true])->assertRedirect();
    expect(DB::table('feature_audit_logs')->where('action', 'beta_access_joined')->count())->toBe(1);

    $this->put(route('profile.beta-access.update'), ['enabled' => false])->assertRedirect();
    expect($user->fresh()->beta_access_joined_at)->toBeNull()
        ->and(DB::table('feature_audit_logs')->where('action', 'beta_access_left')->count())->toBe(1)
        ->and(DB::table('features')->orderBy('id')->get()->toJson())->toBe($legacyBefore);
});

it('requires authentication and valid consent without changing enrollment', function (): void {
    $this->putJson(route('profile.beta-access.update'), ['enabled' => true])->assertUnauthorized();
    $user = User::factory()->create(['role' => UserRole::Student]);
    $this->actingAs($user)->putJson(route('profile.beta-access.update'), [])->assertUnprocessable()->assertJsonValidationErrors('enabled');
    $this->putJson(route('profile.beta-access.update'), ['enabled' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('enabled');
    expect($user->fresh()->beta_access_joined_at)->toBeNull();
});

it('accepts beta participation for faculty and staff profiles without granting developer features', function (UserRole $role): void {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->put(route('profile.beta-access.update'), ['enabled' => true])->assertRedirect();
    expect($user->fresh()->beta_access_joined_at)->not->toBeNull();
    foreach (['faculty-developer-mode', 'admin-developer-mode', 'ai-help-desk-assistant'] as $key) {
        expect(app(FeatureAccessService::class)->decide($key, $user->fresh())->active)->toBeFalse();
    }
})->with([UserRole::Instructor, UserRole::Registrar]);

it('publishes beta availability only for enrolled eligible users including new participants', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user = User::factory()->create(['role' => UserRole::Student]);
    $other = User::factory()->create(['role' => UserRole::Student, 'beta_access_joined_at' => now()]);
    $wrongRole = User::factory()->create(['role' => UserRole::Instructor, 'beta_access_joined_at' => now()]);
    $management = app(FeatureManagementService::class);
    $policy = $management->policyPayload(app(FeatureAccessService::class)->definition('student-grades-preview'));
    $this->actingAs($actor)->put(route('administrators.feature-toggles.policy.update', 'student-grades-preview'), [...$policy, 'release_audience' => 'beta'])->assertRedirect();

    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeFalse()
        ->and(Feature::for($other)->active(StudentGradesPreview::class))->toBeTrue()
        ->and(Feature::for($wrongRole)->active(StudentGradesPreview::class))->toBeFalse();
    $this->actingAs($user)->put(route('profile.beta-access.update'), ['enabled' => true])->assertRedirect();
    expect(Feature::for($user->fresh())->active(StudentGradesPreview::class))->toBeTrue();
    $this->put(route('profile.beta-access.update'), ['enabled' => false])->assertRedirect();
    expect(Feature::for($user->fresh())->active(StudentGradesPreview::class))->toBeFalse()
        ->and(Feature::for($user)->active(StudentClasses::class))->toBeTrue();
});

it('requires both enrollment and selection for private beta and preferences cannot grant access', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $selected = User::factory()->create(['role' => UserRole::Student]);
    $unselected = User::factory()->create(['role' => UserRole::Student, 'beta_access_joined_at' => now()]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'selected']);
    $management = app(FeatureManagementService::class);
    $management->flush();
    $management->assign('student-grades-preview', 'user', (string) $selected->id, 'allow', $actor);
    expect(Feature::for($selected)->active(StudentGradesPreview::class))->toBeFalse();
    $this->actingAs($selected)->put(route('profile.beta-access.update'), ['enabled' => true])->assertRedirect();
    expect(Feature::for($selected->fresh())->active(StudentGradesPreview::class))->toBeTrue();
    $this->actingAs($unselected)->put(route('profile.beta-access.features.update'), ['feature_key' => 'student-grades-preview', 'enabled' => true])->assertRedirect();
    expect(Feature::for($unselected)->active(StudentGradesPreview::class))->toBeFalse();
});

it('respects feature opt-out during beta but ignores it after a stable release', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student, 'beta_access_joined_at' => now()]);
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $management = app(FeatureManagementService::class);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'beta']);
    $management->flush();
    $management->assign('student-grades-preview', 'user', (string) $user->id, 'allow', $actor);
    $this->actingAs($user)->put(route('profile.beta-access.features.update'), ['feature_key' => 'student-grades-preview', 'enabled' => false])->assertRedirect();
    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeFalse();
    $policy = $management->policyPayload(app(FeatureAccessService::class)->definition('student-grades-preview'));
    $management->updatePolicy('student-grades-preview', [...$policy, 'release_audience' => 'stable'], $actor);
    $standard = User::factory()->create(['role' => UserRole::Student]);
    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeTrue()
        ->and(Feature::for($standard)->active(StudentGradesPreview::class))->toBeTrue();
});

it('restores beta preference but cannot join the beta program through feature preferences', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'beta']);
    app(FeatureManagementService::class)->flush();
    $endpoint = route('profile.beta-access.features.update');
    $payload = ['feature_key' => 'student-grades-preview', 'enabled' => true];
    $this->actingAs($user)->putJson($endpoint, $payload)->assertUnprocessable();
    $this->put(route('profile.beta-access.update'), ['enabled' => true])->assertRedirect();
    $this->put($endpoint, [...$payload, 'enabled' => false])->assertRedirect();
    $this->put($endpoint, $payload)->assertRedirect();
    expect(Feature::for($user->fresh())->active(StudentGradesPreview::class))->toBeTrue();
});

it('keeps school beta groups as selection constraints rather than membership enrollment', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $school = School::factory()->create();
    $other = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id, 'beta_access_joined_at' => now()]);
    $group = DB::table('feature_groups')->insertGetId(['name' => 'Student pilot', 'school_id' => $school->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('feature_group_user')->insert(['feature_group_id' => $group, 'user_id' => $user->id]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'selected']);
    $management = app(FeatureManagementService::class);
    $management->flush();
    $management->assign('student-grades-preview', 'group', (string) $group, 'allow', $actor);
    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeTrue();
    $user->school_id = $other->id;
    $user->save();
    $management->flush();
    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeFalse();
});

it('prevents beta preferences changing stable restricted unknown or wrong-role features', function (string $key): void {
    $user = User::factory()->create(['role' => UserRole::Student, 'beta_access_joined_at' => now()]);
    $this->actingAs($user)->putJson(route('profile.beta-access.features.update'), ['feature_key' => $key, 'enabled' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('feature_key');
    expect(DB::table('feature_assignments')->where('source', 'self_service')->count())->toBe(0);
})->with(['student-classes', 'student-developer-mode', 'ai-student-advisor', 'unknown-feature', 'faculty-inbox']);

it('keeps school exclusions off switch and schedules above beta grants', function (): void {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id, 'beta_access_joined_at' => now()]);
    $management = app(FeatureManagementService::class);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'beta']);
    $management->flush();
    $management->assign('student-grades-preview', 'user', (string) $user->id, 'allow', $actor);
    expect(app(FeatureAccessService::class)->decide('student-grades-preview', $user, $other->id)->active)->toBeFalse();
    $management->assign('student-grades-preview', 'school', (string) $school->id, 'deny', $actor);
    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeFalse();
    DB::table('feature_assignments')->where('effect', 'deny')->delete();
    foreach ([['enabled' => false], ['enabled' => true, 'activate_at' => now()->addDay()], ['activate_at' => null, 'retire_at' => now()->subHour()]] as $change) {
        DB::table('feature_definitions')->where('key', 'student-grades-preview')->update($change);
        $management->flush();
        expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeFalse();
    }
});

it('applies stable buckets within beta audience without per-user materialization', function (): void {
    $users = User::factory()->count(20)->create(['role' => UserRole::Student, 'beta_access_joined_at' => now()]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'beta', 'strategy' => 'percentage', 'percentage' => 25]);
    app(FeatureManagementService::class)->flush();
    $definition = app(FeatureAccessService::class)->definition('student-grades-preview');
    foreach ($users as $user) {
        expect(Feature::for($user)->active(StudentGradesPreview::class))
            ->toBe(StableRollout::includes($definition->rollout_seed, 'student-grades-preview', $user->id, $user->school_id, 25));
    }
    $standard = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['percentage' => 100]);
    app(FeatureManagementService::class)->flush();
    expect(Feature::for($standard)->active(StudentGradesPreview::class))->toBeFalse()
        ->and(DB::table('feature_assignments')->count())->toBe(0);
});

it('limits private beta percentage to the selected enrolled cohort', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user = User::factory()->create(['role' => UserRole::Student, 'beta_access_joined_at' => now()]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'selected', 'strategy' => 'percentage', 'percentage' => 0]);
    $management = app(FeatureManagementService::class);
    $management->flush();
    $management->assign('student-grades-preview', 'role', 'student', 'allow', $actor);
    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeFalse();
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['percentage' => 100]);
    $management->flush();
    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeTrue();
});

it('returns beta enrollment and preferences on profiles even before beta features exist', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $this->actingAs($user)->get(route('student.profile'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('beta_access.enrolled', false)->has('beta_access.features', 0));
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'beta']);
    app(FeatureManagementService::class)->flush();
    $this->put(route('profile.beta-access.update'), ['enabled' => true])->assertRedirect();
    $this->get(route('student.profile'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('beta_access.enrolled', true)->where('beta_access.features.0.key', 'student-grades-preview')
        ->where('beta_access.features.0.available', true)->where('beta_access.features.0.enabled', true));
});

it('preserves legacy mode and raw results when an audience is saved before adoption', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['mode' => 'legacy']);
    app(FeatureManagementService::class)->flush();
    Feature::for($user)->activate(StudentGradesPreview::class);
    $before = DB::table('features')->orderBy('id')->get()->toJson();
    $management = app(FeatureManagementService::class);
    $policy = $management->policyPayload(app(FeatureAccessService::class)->definition('student-grades-preview'));
    $management->updatePolicy('student-grades-preview', [...$policy, 'release_audience' => 'beta'], $actor);
    expect(Feature::for($user)->active(StudentGradesPreview::class))->toBeTrue()
        ->and(DB::table('features')->orderBy('id')->get()->toJson())->toBe($before);
});

it('rejects invalid beta audiences and unprivileged policy writers', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $management = app(FeatureManagementService::class);
    foreach (['student-classes', 'student-developer-mode', 'online-college-enrollment'] as $key) {
        $policy = $management->policyPayload(app(FeatureAccessService::class)->definition($key));
        $this->actingAs($actor)->putJson(route('administrators.feature-toggles.policy.update', $key), [...$policy, 'release_audience' => 'beta'])
            ->assertUnprocessable()->assertJsonValidationErrors('release_audience');
    }
    $policy = $management->policyPayload(app(FeatureAccessService::class)->definition('student-grades-preview'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Student]))
        ->putJson(route('administrators.feature-toggles.policy.update', 'student-grades-preview'), [...$policy, 'release_audience' => 'stable'])->assertForbidden();
});

it('validates audience names and preserves explicit audience when older clients omit it', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $management = app(FeatureManagementService::class);
    $key = 'student-grades-preview';
    $policy = $management->policyPayload(app(FeatureAccessService::class)->definition($key));
    $this->actingAs($actor)->putJson(route('administrators.feature-toggles.policy.update', $key), [...$policy, 'release_audience' => 'everyone'])
        ->assertUnprocessable()->assertJsonValidationErrors('release_audience');
    $management->updatePolicy($key, [...$policy, 'release_audience' => 'beta'], $actor);
    $updated = $management->policyPayload(app(FeatureAccessService::class)->definition($key));
    unset($updated['release_audience']);
    $this->put(route('administrators.feature-toggles.policy.update', $key), $updated)->assertRedirect();
    expect(app(FeatureAccessService::class)->definition($key)->release_audience)->toBe('beta');
});

it('does not let legacy experimental endpoint grant or clear audience-based preferences', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student, 'beta_access_joined_at' => now()]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'beta']);
    app(FeatureManagementService::class)->flush();
    $this->actingAs($user)->put(route('profile.beta-access.features.update'), ['feature_key' => 'student-grades-preview', 'enabled' => false])->assertRedirect();
    $this->postJson(route('student.profile.experimental-features'), ['features' => ['onboarding-student-grades-preview']])
        ->assertUnprocessable()->assertJsonValidationErrors('features');
    $this->post(route('student.profile.experimental-features'), ['features' => []])->assertRedirect();
    expect(DB::table('feature_assignments')->where('feature_key', 'student-grades-preview')->where('source', 'self_service')->value('effect'))->toBe('deny');
});

it('reports real current and managed beta access without writing evaluations for preview subjects', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $beta = User::factory()->create(['role' => UserRole::Student, 'beta_access_joined_at' => now()]);
    $standard = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['release_audience' => 'beta']);
    $management = app(FeatureManagementService::class);
    $management->flush();
    $endpoint = route('administrators.feature-toggles.preview', 'student-grades-preview');
    $this->actingAs($actor)->getJson($endpoint.'?user_id='.$beta->id)->assertOk()->assertJsonPath('current', true)->assertJsonPath('managed.active', true);
    $this->getJson($endpoint.'?user_id='.$standard->id)->assertOk()->assertJsonPath('current', false)->assertJsonPath('managed.reason', 'Beta Access enrollment required');
    $policy = $management->policyPayload(app(FeatureAccessService::class)->definition('student-grades-preview'));
    $this->postJson(route('administrators.feature-toggles.impact', 'student-grades-preview'), [...$policy, 'release_audience' => 'stable'])
        ->assertOk()->assertJsonPath('gaining', 1)->assertJsonPath('losing', 0)->assertJsonPath('unchanged', 1);
    expect(DB::table('features')->where('name', StudentGradesPreview::class)->whereIn('scope', [Feature::serializeScope($beta), Feature::serializeScope($standard)])->count())->toBe(0);
});

it('enforces beta distribution on faculty routes and shared navigation using the same decisions', function (): void {
    $user = User::factory()->create(['role' => UserRole::Instructor, 'faculty_id_number' => 'BETA-FACULTY-01']);
    DB::table('feature_definitions')->where('key', 'faculty-forms')->update(['release_audience' => 'beta']);
    app(FeatureManagementService::class)->flush();
    $this->actingAs($user)->getJson(route('faculty.forms'))->assertForbidden();
    $this->put(route('profile.beta-access.update'), ['enabled' => true])->assertRedirect();
    $this->get(route('faculty.forms'))->assertOk()->assertInertia(fn ($page) => $page->where('featureFlags.enabledRoutes.forms', true));
    $this->put(route('profile.beta-access.update'), ['enabled' => false])->assertRedirect();
    $this->getJson(route('faculty.forms'))->assertForbidden();
});
