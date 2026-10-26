<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModulePermissionService;
use App\Modules\ModuleStateRepository;
use App\Services\TenantContext;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Koakademy\SchoolNotes\Models\SchoolNote;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    $this->extensionStatuses = storage_path('framework/testing/starter-'.bin2hex(random_bytes(6)).'.json');
    File::ensureDirectoryExists(dirname($this->extensionStatuses));
    File::put($this->extensionStatuses, json_encode(['SchoolNotes' => true], JSON_THROW_ON_ERROR));
    config(['modules.scan.enabled' => true, 'modules.statuses-file' => $this->extensionStatuses]);
    app()->forgetInstance(ModuleStateRepository::class);
    app()->forgetInstance(ModuleManifestRepository::class);
    app()->forgetInstance(App\Modules\ModuleAccessService::class);
    app(ModulePermissionService::class)->sync();
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake(['https://api.github.com/repos/yukazakiri/koakademy/*' => Illuminate\Support\Facades\Http::response([])]);
});

afterEach(function (): void {
    File::delete([$this->extensionStatuses, $this->extensionStatuses.'.lock']);
});

it('shows and creates notes only in the authorized school', function (): void {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Registrar, 'school_id' => $school->id]);
    $user->givePermissionTo(['school-notes.view', 'school-notes.create']);
    app(TenantContext::class)->setCurrentSchool($school);
    SchoolNote::query()->create(['school_id' => $other->id, 'created_by' => $user->id, 'body' => 'Other school private note']);

    $this->actingAs($user)->post(route('administrators.school-notes.store'), [
        'body' => 'Library orientation is on Thursday.',
        'school_id' => $other->id,
    ])->assertRedirect(route('administrators.school-notes.index'));

    $this->assertDatabaseHas('school_notes_extension_notes', [
        'school_id' => $school->id, 'created_by' => $user->id, 'body' => 'Library orientation is on Thursday.',
    ]);
    $this->actingAs($user)->get(route('administrators.school-notes.index'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('SchoolNotes/Index', false)
        ->has('notes', 1)->where('notes.0.body', 'Library orientation is on Thursday.'));
});

it('denies insufficient permissions and validates submitted notes', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Registrar, 'school_id' => $school->id]);
    app(TenantContext::class)->setCurrentSchool($school);
    $this->actingAs($user)->get(route('administrators.school-notes.index'))->assertForbidden();
    $this->actingAs($user)->post(route('administrators.school-notes.store'), ['body' => 'Denied'])->assertForbidden();
    $user->givePermissionTo('school-notes.create');
    $this->actingAs($user)->postJson(route('administrators.school-notes.store'), ['body' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->assertDatabaseCount('school_notes_extension_notes', 0);
});

it('rejects a tenant context outside the users organizations', function (): void {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Registrar, 'school_id' => $school->id]);
    $user->givePermissionTo('school-notes.view');
    app(TenantContext::class)->setCurrentSchool($other);

    $this->actingAs($user)->get(route('administrators.school-notes.index'))->assertForbidden();
});

it('blocks direct read and write routes when the extension is disabled', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    app(ModuleStateRepository::class)->setEnabled('SchoolNotes', false);

    $this->actingAs($user)->get(route('administrators.school-notes.index'))->assertNotFound();
    $this->actingAs($user)->post(route('administrators.school-notes.store'), ['body' => 'Not saved'])->assertNotFound();
    $this->assertDatabaseCount('school_notes_extension_notes', 0);
});
