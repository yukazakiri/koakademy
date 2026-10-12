<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\StableRollout;
use App\Features\Toggles\OnlineCollegeEnrollment;
use App\Features\Toggles\StudentClasses;
use App\Models\School;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\FeatureManagementService;
use App\Services\FeatureToggleRegistry;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    $this->withoutVite();
    DB::table('feature_definitions')->update(['mode' => 'enforced']);
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();
});

it('gives baseline functions to every intended role without enabling beta or developer features', function (): void {
    foreach (UserRole::cases() as $role) {
        $user = User::factory()->create(['role' => $role]);
        foreach (FeatureToggleRegistry::allKeys() as $key) {
            $catalog = FeatureToggleRegistry::definition($key);
            $expected = $catalog['public'] || ($catalog['default_enabled'] && in_array($role->value, $catalog['roles'], true));
            expect(Feature::for($user)->active(FeatureToggleRegistry::classForKey($key)))->toBe($expected);
        }
    }
});

it('resolves public enrollment as a boolean and does not mutate legacy evaluations', function (): void {
    $before = DB::table('features')->orderBy('id')->get()->toJson();
    expect(Feature::for(null)->value(OnlineCollegeEnrollment::class))->toBeTrue();
    expect(DB::table('features')->orderBy('id')->get()->toJson())->toBe($before);
    DB::table('feature_definitions')->where('key', 'online-college-enrollment')->update(['enabled' => false]);
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();
    expect(Feature::for(null)->active(OnlineCollegeEnrollment::class))->toBeFalse();
});

it('preserves stored legacy behavior until explicitly adopted', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    Feature::for($user)->deactivate(StudentClasses::class);
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['mode' => 'legacy']);
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();
    expect(Feature::for($user)->active(StudentClasses::class))->toBeFalse();
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['mode' => 'enforced']);
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();
    expect(Feature::for($user)->active(StudentClasses::class))->toBeTrue();
    expect(DB::table('features')->where('name', StudentClasses::class)->where('scope', Feature::serializeScope($user))->value('value'))->toBe('false');
});

it('school exclusions beat individual grants and kill switch preserves targets', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id]);
    $service = app(FeatureManagementService::class);
    $service->assign('student-classes', 'user', (string) $user->id, 'allow', $actor);
    $service->assign('student-classes', 'school', (string) $school->id, 'deny', $actor);
    expect(Feature::for($user)->active(StudentClasses::class))->toBeFalse();
    DB::table('feature_assignments')->where('subject_type', 'school')->delete();
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['enabled' => false]);
    $service->flush();
    expect(Feature::for($user)->active(StudentClasses::class))->toBeFalse();
    expect(DB::table('feature_assignments')->count())->toBe(1);
});

it('beta membership stops applying when removed or outside group school', function (): void {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id]);
    $groupId = DB::table('feature_groups')->insertGetId(['name' => 'Pilot', 'school_id' => $school->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('feature_group_user')->insert(['feature_group_id' => $groupId, 'user_id' => $user->id]);
    app(FeatureManagementService::class)->assign('student-grades-preview', 'group', (string) $groupId, 'allow', User::factory()->create(['role' => UserRole::SuperAdmin]));
    expect(app(FeatureAccessService::class)->decide('student-grades-preview', $user)->active)->toBeTrue();
    $user->update(['school_id' => $other->id]);
    app(FeatureAccessService::class)->reset();
    expect(app(FeatureAccessService::class)->decide('student-grades-preview', $user)->active)->toBeFalse();
    $user->update(['school_id' => $school->id]);
    DB::table('feature_group_user')->where('user_id', $user->id)->delete();
    app(FeatureAccessService::class)->reset();
    expect(app(FeatureAccessService::class)->decide('student-grades-preview', $user)->active)->toBeFalse();
});

it('percentage buckets are stable monotonic and reversible without rows', function (): void {
    for ($id = 1; $id <= 200; $id++) {
        expect(StableRollout::includes('seed', 'student-classes', $id, 1, 0))->toBeFalse();
        expect(StableRollout::includes('seed', 'student-classes', $id, 1, 100))->toBeTrue();
        if (StableRollout::includes('seed', 'student-classes', $id, 1, 10)) {
            expect(StableRollout::includes('seed', 'student-classes', $id, 1, 50))->toBeTrue();
        }
    }
    $user = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['strategy' => 'percentage', 'percentage' => 100]);
    app(FeatureAccessService::class)->reset();
    expect(Feature::for($user)->active(StudentClasses::class))->toBeTrue();
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['percentage' => 0]);
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();
    expect(Feature::for($user)->active(StudentClasses::class))->toBeFalse();
    expect(DB::table('features')->where('scope', Feature::serializeScope($user))->count())->toBe(0);
});

it('serves new management page with role defaults and real counts', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $this->actingAs($actor)->get(route('administrators.feature-toggles.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('administrators/feature-toggles/index')
            ->has('features', count(FeatureToggleRegistry::allKeys()))->where('features.0.catalog.default_enabled', true)
            ->where('features.0.assignment_count', 0)->has('groups', 0));
});

it('blocks portal users without distribution authority', function (UserRole $role): void {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->getJson(route('administrators.feature-toggles.details', 'student-classes'))->assertForbidden();
    $this->postJson(route('administrators.feature-toggles.assignments.store', 'student-classes'), ['subject_type' => 'role', 'subject_key' => 'student', 'effect' => 'allow'])->assertForbidden();
})->with([UserRole::Student, UserRole::Instructor, UserRole::Registrar, UserRole::Cashier]);

it('rejects stale policy saves and wrong-role targeting', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $policy = app(FeatureManagementService::class)->policyPayload(app(FeatureAccessService::class)->definition('student-classes'));
    $this->actingAs($actor)->putJson(route('administrators.feature-toggles.policy.update', 'student-classes'), $policy)->assertRedirect();
    $this->putJson(route('administrators.feature-toggles.policy.update', 'student-classes'), $policy)->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->postJson(route('administrators.feature-toggles.assignments.store', 'student-classes'), ['subject_type' => 'role', 'subject_key' => 'instructor', 'effect' => 'allow'])->assertUnprocessable()->assertJsonValidationErrors('subject_key');
    expect(DB::table('feature_audit_logs')->where('action', 'policy_updated')->count())->toBe(1);
});

it('clears explicit user assignments without deleting preserved evaluations', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user = User::factory()->create(['role' => UserRole::Student]);
    app(FeatureManagementService::class)->assign('student-classes', 'user', (string) $user->id, 'deny', $actor);
    Feature::for($user)->activate(StudentClasses::class);
    $this->actingAs($actor)->postJson(route('administrators.feature-toggles.purge-overrides', 'student-classes'))->assertOk();
    expect(DB::table('feature_assignments')->count())->toBe(0);
    expect(DB::table('features')->where('scope', Feature::serializeScope($user))->count())->toBe(1);
});

it('rejects cross-school group membership', function (): void {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $user = User::factory()->create(['school_id' => $other->id]);
    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))->postJson(route('administrators.feature-toggles.groups.store'),
        ['name' => 'School beta', 'school_id' => $school->id, 'user_ids' => [$user->id]])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
    expect(DB::table('feature_groups')->count())->toBe(0);
});

it('allows approved beta opt-in without overriding administrator exclusions', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    DB::table('feature_definitions')->where('key', 'student-grades-preview')->update(['allow_opt_in' => true]);
    app(FeatureAccessService::class)->reset();
    $endpoint = route('student.profile.experimental-features');
    $this->actingAs($user)->postJson($endpoint, ['features' => ['onboarding-student-grades-preview']])->assertRedirect();
    expect(app(FeatureAccessService::class)->decide('student-grades-preview', $user)->active)->toBeTrue();
    app(FeatureManagementService::class)->assign('student-grades-preview', 'user', (string) $user->id, 'deny', $actor);
    expect(app(FeatureAccessService::class)->decide('student-grades-preview', $user)->active)->toBeFalse();
    $this->postJson($endpoint, ['features' => ['onboarding-faculty-grades']])->assertUnprocessable();
    $this->postJson($endpoint, ['features' => ['onboarding-student-developer-mode']])->assertUnprocessable();
    $this->postJson($endpoint, [])->assertUnprocessable();
    expect(DB::table('feature_assignments')->where('feature_key', 'student-grades-preview')->count())->toBe(2);
});

it('rejects unauthorized active school context even for allowed role', function (): void {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id]);
    expect(app(FeatureAccessService::class)->decide('student-classes', $user, $other->id)->active)->toBeFalse();
});

it('migration preserves existing-install rows and initializes fresh role defaults', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    Feature::for($user)->activate(StudentClasses::class);
    $before = DB::table('features')->orderBy('id')->get()->toJson();
    $migration = require database_path('migrations/2026_10_12_114248_create_feature_distribution_tables.php');
    $migration->down();
    $migration->up();
    expect(DB::table('feature_definitions')->where('mode', 'legacy')->count())->toBe(count(FeatureToggleRegistry::allKeys()));
    expect(DB::table('features')->orderBy('id')->get()->toJson())->toBe($before);
    $migration->down();
    DB::table('users')->delete();
    DB::table('general_settings')->update(['is_setup' => false]);
    $migration->up();
    expect(DB::table('feature_definitions')->where('mode', 'enforced')->count())->toBe(count(FeatureToggleRegistry::allKeys()));
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();
    $newStudent = User::factory()->create(['role' => UserRole::Student]);
    expect(Feature::for($newStudent)->active(StudentClasses::class))->toBeTrue();
});
