<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\StudentClasses;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\FeatureManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    $this->withoutVite();
    // The suite default models an upgraded installation; these cases exercise
    // managed release behaviour, so opt the two features under test in.
    DB::table('feature_definitions')->whereIn('key', ['student-classes', 'student-tuition'])->update(['mode' => 'enforced']);
    app(FeatureAccessService::class)->reset();
});

afterEach(function (): void {
    app(FeatureAccessService::class)->reset();
});

it('redirects with a notice when a feature is outside the current release', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-tuition')->update(['strategy' => 'percentage', 'percentage' => 0]);
    app(FeatureAccessService::class)->reset();

    $this->actingAs($user)->get(route('student.tuition.index'))->assertRedirect(route('student.dashboard'));

    // Rendered by the destination page, then consumed exactly once.
    $this->actingAs($user)->get(route('student.dashboard'))
        ->assertInertia(fn ($page) => $page->where('featureNotice.title', 'Not available yet')
            ->where('featureNotice.message', 'This feature is not part of your current release. An administrator can release it for you.'));
    $this->actingAs($user)->get(route('student.dashboard'))->assertInertia(fn ($page) => $page->where('featureNotice', null));
});

it('keeps 403 when an administrator deliberately excluded the user', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    app(FeatureManagementService::class)->assign('student-tuition', 'user', (string) $user->id, 'deny', User::factory()->create(['role' => UserRole::SuperAdmin]));

    $this->actingAs($user)->get(route('student.tuition.index'))->assertForbidden();
});

it('keeps 403 for a role that is not entitled at all', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Cashier]))
        ->get(route('student.tuition.index'))->assertForbidden();
});

it('still serves the feature once it is inside the release window', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-tuition')->update(['strategy' => 'percentage', 'percentage' => 100]);
    app(FeatureAccessService::class)->reset();

    $this->actingAs($user)->get(route('student.tuition.index'))->assertOk();
});

it('honours scheduled release and retirement windows', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);

    DB::table('feature_definitions')->where('key', 'student-classes')->update(['activate_at' => now()->addDay()]);
    app(FeatureAccessService::class)->reset();
    expect(app(FeatureAccessService::class)->decide('student-classes', $user)->denial->value)->toBe('not_released');

    DB::table('feature_definitions')->where('key', 'student-classes')->update(['activate_at' => now()->subDay(), 'retire_at' => now()->subHour()]);
    app(FeatureAccessService::class)->reset();
    $retired = app(FeatureAccessService::class)->decide('student-classes', $user);
    expect($retired->active)->toBeFalse()->and($retired->reason)->toBe('Retired by administrator');

    DB::table('feature_definitions')->where('key', 'student-classes')->update(['retire_at' => null]);
    app(FeatureAccessService::class)->reset();
    expect(app(FeatureAccessService::class)->decide('student-classes', $user)->active)->toBeTrue();
});

it('rejects a retirement that precedes release', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $policy = app(FeatureManagementService::class)->policyPayload(app(FeatureAccessService::class)->definition('student-classes'));

    $this->actingAs($actor)->putJson(route('administrators.feature-toggles.policy.update', 'student-classes'), [
        ...$policy, 'activate_at' => now()->addDay()->toDateTimeString(), 'retire_at' => now()->toDateTimeString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('retire_at');
});

it('reports adoption impact without changing access', function (): void {
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['mode' => 'legacy']);
    app(FeatureAccessService::class)->reset();
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    User::factory()->count(3)->create(['role' => UserRole::Student]);

    $policy = app(FeatureManagementService::class)->policyPayload(app(FeatureAccessService::class)->definition('student-classes'));
    $impact = $this->actingAs($actor)->postJson(route('administrators.feature-toggles.impact', 'student-classes'), $policy)->assertOk()->json();

    expect($impact)->toHaveKeys(['eligible', 'sampled', 'gaining', 'losing', 'unchanged'])
        ->and($impact['eligible'])->toBe(3)
        ->and($impact['sampled'])->toBe(3);
    // A report must never adopt on its own.
    expect(DB::table('feature_definitions')->where('key', 'student-classes')->value('mode'))->toBe('legacy');
    // HTTP shared props can legitimately resolve the actor's flags, but preview
    // must never persist values for the three subjects being inspected.
    expect(DB::table('features')->where('name', StudentClasses::class)->where('scope', 'like', 'App\\Models\\User|%')->count())->toBe(1);
});

it('compares draft policy with stored legacy values without materializing subject evaluations', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $users = User::factory()->count(3)->create(['role' => UserRole::Student]);
    Feature::for($users[0])->activate(StudentClasses::class);
    Feature::for($users[1])->deactivate(StudentClasses::class);
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['mode' => 'legacy']);
    app(FeatureAccessService::class)->reset();
    $service = app(FeatureManagementService::class);
    $policy = $service->policyPayload(app(FeatureAccessService::class)->definition('student-classes'));
    $before = DB::table('features')->orderBy('id')->get()->toJson();
    $result = $service->impact('student-classes', $actor, [...$policy, 'strategy' => 'percentage', 'percentage' => 100]);
    expect($result['gaining'])->toBe(2)->and($result['losing'])->toBe(0)->and($result['unchanged'])->toBe(1);
    expect(DB::table('features')->orderBy('id')->get()->toJson())->toBe($before);
    $service->updatePolicy('student-classes', [...$policy, 'mode' => 'enforced', 'strategy' => 'percentage', 'percentage' => 100], $actor);
    expect(Feature::for($users[1])->active(StudentClasses::class))->toBeTrue();
    expect(DB::table('features')->orderBy('id')->get()->toJson())->toBe($before);
});

it('persists explicit timezone schedules and permits retirement without a release time', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $policy = app(FeatureManagementService::class)->policyPayload(app(FeatureAccessService::class)->definition('student-classes'));
    $this->actingAs($actor)->putJson(route('administrators.feature-toggles.policy.update', 'student-classes'), [
        ...$policy, 'activate_at' => '2027-01-01T09:00:00+08:00', 'retire_at' => '2027-01-01T10:00:00+08:00',
    ])->assertRedirect();
    $saved = app(FeatureManagementService::class)->policyPayload(app(FeatureAccessService::class)->definition('student-classes'));
    expect($saved['activate_at'])->toBe('2027-01-01T01:00:00+00:00')->and($saved['retire_at'])->toBe('2027-01-01T02:00:00+00:00');
    $this->putJson(route('administrators.feature-toggles.policy.update', 'student-classes'), [...$saved, 'activate_at' => null])->assertRedirect();
});

it('activates at the exact release boundary and retires at the exact end boundary', function (): void {
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user = User::factory()->create(['role' => UserRole::Student]);
    $service = app(FeatureManagementService::class);
    $policy = $service->policyPayload(app(FeatureAccessService::class)->definition('student-classes'));
    $service->updatePolicy('student-classes', [...$policy, 'activate_at' => '2027-01-01T00:00:00Z', 'retire_at' => '2027-01-01T01:00:00Z'], $actor);
    $this->travelTo(Illuminate\Support\Carbon::parse('2026-12-31T23:59:59Z'));
    expect(app(FeatureAccessService::class)->decide('student-classes', $user)->active)->toBeFalse();
    $this->travelTo(Illuminate\Support\Carbon::parse('2027-01-01T00:00:00Z'));
    expect(app(FeatureAccessService::class)->decide('student-classes', $user)->active)->toBeTrue();
    $this->travelTo(Illuminate\Support\Carbon::parse('2027-01-01T01:00:00Z'));
    expect(app(FeatureAccessService::class)->decide('student-classes', $user)->active)->toBeFalse();
    $this->travelBack();
});

it('preserves rollout notice through unrelated JSON requests', function (): void {
    Route::middleware('web')->get('/feature-notice-probe', fn () => response()->json(['ok' => true]));
    $user = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-tuition')->update(['strategy' => 'percentage', 'percentage' => 0]);
    $this->actingAs($user)->get(route('student.tuition.index'))->assertRedirect();
    $this->getJson('/feature-notice-probe')->assertOk()->assertSessionHas('feature_notice');
    $this->get(route('student.dashboard'))->assertInertia(fn ($page) => $page->where('featureNotice.title', 'Not available yet'));
});

it('never redirects failed mutations or JSON rollout requests as if they succeeded', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    DB::table('feature_definitions')->where('key', 'student-tuition')->update(['strategy' => 'percentage', 'percentage' => 0]);
    $this->actingAs($user)->getJson(route('student.tuition.index'))->assertForbidden();
    $this->post(route('student.tuition.update-requests.store'), [])->assertForbidden();
});

it('rejects unauthorized impact requests and stale drafts without changing policy', function (): void {
    $policy = app(FeatureManagementService::class)->policyPayload(app(FeatureAccessService::class)->definition('student-classes'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Registrar]))
        ->postJson(route('administrators.feature-toggles.impact', 'student-classes'), $policy)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
        ->postJson(route('administrators.feature-toggles.impact', 'student-classes'), [...$policy, 'revision' => $policy['revision'] + 1])
        ->assertUnprocessable()->assertJsonValidationErrors('revision');
    expect(DB::table('feature_audit_logs')->count())->toBe(0);
});

it('keeps public adoption comparisons read-only with native legacy envelope semantics', function (): void {
    $key = 'online-college-enrollment';
    $actor = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $service = app(FeatureManagementService::class);
    $policy = $service->policyPayload(app(FeatureAccessService::class)->definition($key));
    $before = DB::table('features')->orderBy('id')->get()->toJson();
    $impact = $service->impact($key, $actor, [...$policy, 'enabled' => false]);
    expect($impact['public_current'])->toBeTrue()->and($impact['public_proposed'])->toBeFalse();
    expect(DB::table('features')->orderBy('id')->get()->toJson())->toBe($before);
});

it('adds schedule columns to already-installed distribution schema without changing policy', function (): void {
    $migration = require database_path('migrations/2026_10_12_181713_add_release_window_to_feature_definitions.php');
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['enabled' => false, 'revision' => 7]);
    $migration->down();
    $migration->up();
    $row = DB::table('feature_definitions')->where('key', 'student-classes')->first();
    expect((bool) $row->enabled)->toBeFalse()->and((int) $row->revision)->toBe(7)
        ->and($row->activate_at)->toBeNull()->and($row->retire_at)->toBeNull();
});
