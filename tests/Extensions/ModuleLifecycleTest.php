<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Plugins\DiscoveredModulesPlugin;
use App\Models\School;
use App\Models\User;
use App\Modules\CompatibilityChecker;
use App\Modules\ModuleAccessService;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModulePermissionService;
use App\Modules\ModuleStateRepository;
use App\Services\ModuleAdminNavigationService;
use Filament\Panel;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Koakademy\SchoolNotes\Jobs\CountSchoolNotes;
use Koakademy\SchoolNotes\Livewire\NoteSummary;
use Koakademy\SchoolNotes\Models\SchoolNote;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->extensionStatuses = storage_path('framework/testing/extensions-'.bin2hex(random_bytes(6)).'.json');
    File::ensureDirectoryExists(dirname($this->extensionStatuses));
    $statuses = json_decode(File::get(base_path('modules_statuses.json')), true, 512, JSON_THROW_ON_ERROR);
    $statuses['SchoolNotes'] = true;
    File::put($this->extensionStatuses, json_encode($statuses, JSON_THROW_ON_ERROR));
    config(['modules.scan.enabled' => true, 'modules.statuses-file' => $this->extensionStatuses]);
    foreach ([ModuleStateRepository::class, ModuleManifestRepository::class, ModuleAccessService::class, CompatibilityChecker::class] as $class) {
        app()->forgetInstance($class);
    }
    app(ModuleAdminNavigationService::class)->forgetCache();
    app(ModulePermissionService::class)->sync();
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake(['https://api.github.com/repos/yukazakiri/koakademy/*' => Illuminate\Support\Facades\Http::response([])]);
});

afterEach(function (): void {
    File::delete([$this->extensionStatuses, $this->extensionStatuses.'.lock']);
});

it('discovers an installed Composer package with a different package and namespace identity', function (): void {
    $module = app(ModuleManifestRepository::class)->installedModule('SchoolNotes');
    expect($module)->not->toBeNull()
        ->and($module->path)->toBe(realpath(base_path('vendor/koakademy/school-notes-starter')))
        ->and($module->frontendPath())->toBe('vendor/koakademy/school-notes-starter')
        ->and(app(ModuleManifestRepository::class)->installedModule('koakademy/school-notes-starter')?->path)->toBe($module->path);
    $this->assertDatabaseHas('migrations', ['migration' => '2026_10_26_000001_create_school_notes_extension_notes_table']);
    $this->artisan('modules:validate', ['--all' => true])->assertSuccessful();
});

it('provisions permissions idempotently without assigning them to users', function (): void {
    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->artisan('modules:sync-permissions')->assertSuccessful();
    $this->artisan('modules:sync-permissions')->assertSuccessful();

    expect(Permission::where('name', 'school-notes.view')->where('guard_name', 'web')->count())->toBe(1);
    expect($user->fresh()->hasPermissionTo('school-notes.view'))->toBeFalse();
});

it('resolves package navigation from its actual path and filters cached routes after disabling', function (): void {
    $service = app(ModuleAdminNavigationService::class);
    $route = collect($service->getRoutes())->firstWhere('module', 'SchoolNotes');
    expect($route)->not->toBeNull()->and($route['modulePath'])->toBe('vendor/koakademy/school-notes-starter');
    app(ModuleStateRepository::class)->setEnabled('SchoolNotes', false);

    expect(collect($service->getRoutes())->where('module', 'SchoolNotes'))->toBeEmpty();
});

it('registers a package Filament plugin using its PSR-4 namespace', function (): void {
    $panel = Panel::make()->id('extension-contract-test');
    DiscoveredModulesPlugin::make()->register($panel);

    expect($panel->hasPlugin('school-notes-starter'))->toBeTrue();
    app(ModuleStateRepository::class)->setEnabled('SchoolNotes', false);
    $disabledPanel = Panel::make()->id('disabled-extension-contract-test');
    DiscoveredModulesPlugin::make()->register($disabledPanel);
    expect($disabledPanel->hasPlugin('school-notes-starter'))->toBeFalse();
});

it('blocks a disabled legacy Composer package on direct admin routes', function (): void {
    app(ModuleStateRepository::class)->setEnabled('Forms', false);
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)->get(portalUrlForAdministrators('/administrators/forms/create'))->assertNotFound();
});

it('does not expose an auto-discovered package when vendor module scanning is off', function (): void {
    config(['modules.scan.enabled' => false]);
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)->get(portalUrlForAdministrators('/administrators/forms/create'))->assertNotFound();
});

it('blocks a Livewire update after its owning extension is disabled', function (): void {
    $component = Livewire::test(NoteSummary::class)->assertSee('School notes available');
    app(ModuleStateRepository::class)->setEnabled('SchoolNotes', false);

    $component->call('increment')->assertNotFound();
});

it('releases queued extension work while disabled and executes it after reactivation', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    SchoolNote::query()->create(['school_id' => $school->id, 'created_by' => $user->id, 'body' => 'For this school']);
    config(['queue.connections.database.connection' => 'sqlite']);
    $queue = Queue::connection('database');
    $queue->push(new CountSchoolNotes($school->id));
    $job = $queue->pop();
    expect($job)->not->toBeNull();
    app(ModuleStateRepository::class)->setEnabled('SchoolNotes', false);
    app(CallQueuedHandler::class)->call($job, $job->payload()['data']);

    expect($job->isReleased())->toBeTrue()->and(Cache::has('school-notes:count:'.$school->id))->toBeFalse();
    expect(DB::table('jobs')->count())->toBe(1);
    app(ModuleStateRepository::class)->setEnabled('SchoolNotes', true);
    $this->travel(61)->seconds();
    $next = $queue->pop();
    expect($next)->not->toBeNull();
    app(CallQueuedHandler::class)->call($next, $next->payload()['data']);
    expect(Cache::get('school-notes:count:'.$school->id))->toBe(1)->and(DB::table('jobs')->count())->toBe(0);
});

it('rejects synchronous extension work while disabled', function (): void {
    app(ModuleStateRepository::class)->setEnabled('SchoolNotes', false);

    expect(fn () => CountSchoolNotes::dispatchSync(123))->toThrow(RuntimeException::class, 'unavailable');
    expect(Cache::has('school-notes:count:123'))->toBeFalse();
});
