<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Classes\RelationManagers\ClassEnrollmentsRelationManager;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\School;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

it('avoids N+1 department/user counts in OrganizationController::index()', function (): void {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    for ($i = 1; $i <= 5; $i++) {
        School::factory()->create(['name' => "Org {$i}", 'code' => "ORG{$i}", 'is_active' => true]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($admin)
        ->withoutMiddleware(HandleInertiaRequests::class)
        ->getJson('/api/organizations');

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    expect($response->json('organizations'))->toHaveCount(5)
        ->and($queryCount)->toBeLessThanOrEqual(8);
});

it('uses async search without preload for student_id select in ClassEnrollmentsRelationManager', function (): void {
    $manager = new ClassEnrollmentsRelationManager;
    $schema = $manager->form(Schema::make());

    $select = collect($schema->getComponents())
        ->first(fn (mixed $component): bool => $component instanceof Select && $component->getName() === 'student_id');

    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->isSearchable())->toBeTrue()
        ->and($select->isPreloaded())->toBeFalse();
});

it('aggregates role distribution in SQL without loading all User models in AdministratorUserManagementController', function (): void {
    User::factory()->count(3)->create(['role' => UserRole::Student]);
    User::factory()->count(2)->create(['role' => UserRole::Instructor]);
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $source = (string) file_get_contents(app_path('Http/Controllers/AdministratorUserManagementController.php'));
    expect($source)->not->toContain("User::query()->with('roles')->get()->groupBy");

    $this->actingAs($admin)
        ->get(route('administrators.users.index'))
        ->assertOk();
});
