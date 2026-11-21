<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Rooms\Pages\ViewRoom;
use App\Models\Room;
use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function (): void {
    $this->school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($this->school);

    $this->user = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);

    foreach (['ViewAny:Room', 'View:Room', 'Create:Room', 'Update:Room', 'Delete:Room'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }

    actingAs($this->user);
});

it('renders the Inertia rooms index page with filtering and stats', function (): void {
    Room::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Computer Lab 1',
        'class_code' => 'CL-101',
        'type' => 'computer_lab',
        'capacity' => 35,
        'building' => 'Annex A',
        'is_active' => true,
    ]);

    Room::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Room 202',
        'class_code' => 'R-202',
        'type' => 'lecture',
        'capacity' => 45,
        'building' => 'Main Building',
        'is_active' => false,
    ]);

    $this->get(route('administrators.rooms.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/rooms/index')
            ->has('rooms', 2)
            ->where('stats.total', 2)
            ->where('stats.active', 1)
            ->where('stats.inactive', 1)
            ->where('stats.total_capacity', 35)
        );

    $this->get(route('administrators.rooms.index', ['search' => 'Computer', 'status' => 'active', 'type' => 'computer_lab']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/rooms/index')
            ->has('rooms', 1)
            ->where('rooms.0.name', 'Computer Lab 1')
        );
});

it('creates, updates, toggles status, and deletes a room via Inertia routes', function (): void {
    $this->post(route('administrators.rooms.store'), [
        'school_id' => $this->school->id,
        'name' => 'Science Lab 3',
        'class_code' => 'SL-301',
        'type' => 'laboratory',
        'capacity' => 30,
        'building' => 'Science Wing',
        'is_active' => true,
    ])->assertRedirect(route('administrators.rooms.index'));

    $room = Room::query()->where('name', 'Science Lab 3')->firstOrFail();

    expect($room->class_code)->toBe('SL-301')
        ->and($room->type)->toBe('laboratory')
        ->and($room->capacity)->toBe(30)
        ->and($room->building)->toBe('Science Wing')
        ->and($room->is_active)->toBeTrue();

    $this->put(route('administrators.rooms.update', $room), [
        'school_id' => $this->school->id,
        'name' => 'Science Lab 3A',
        'class_code' => 'SL-301A',
        'type' => 'laboratory',
        'capacity' => 36,
        'building' => 'North Wing',
        'is_active' => true,
    ])->assertRedirect(route('administrators.rooms.index'));

    $room->refresh();
    expect($room->name)->toBe('Science Lab 3A')
        ->and($room->capacity)->toBe(36)
        ->and($room->building)->toBe('North Wing');

    $this->patch(route('administrators.rooms.toggle-status', $room))
        ->assertRedirect(route('administrators.rooms.index'));

    expect($room->fresh()?->is_active)->toBeFalse();

    $this->delete(route('administrators.rooms.destroy', $room))
        ->assertRedirect(route('administrators.rooms.index'));

    assertDatabaseMissing('rooms', ['id' => $room->id]);
});

it('lists, creates, views, edits, and toggles status of rooms in Filament', function (): void {
    Filament::setCurrentPanel('admin');

    $existing = Room::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Room 101',
        'class_code' => 'R-101',
        'type' => 'lecture',
        'capacity' => 40,
        'building' => 'Main Building',
        'is_active' => true,
    ]);

    Livewire::test(ListRooms::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$existing]);

    Livewire::test(CreateRoom::class)
        ->fillForm([
            'name' => 'Multimedia Lab',
            'class_code' => 'ML-201',
            'type' => 'computer_lab',
            'capacity' => 28,
            'building' => 'IT Building',
            'school_id' => $this->school->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Room::class, [
        'name' => 'Multimedia Lab',
        'class_code' => 'ML-201',
        'type' => 'computer_lab',
        'capacity' => 28,
        'building' => 'IT Building',
    ]);

    Livewire::test(ViewRoom::class, ['record' => $existing->getKey()])
        ->assertSuccessful();

    Livewire::test(EditRoom::class, ['record' => $existing->getKey()])
        ->fillForm([
            'name' => 'Room 101 Updated',
            'capacity' => 50,
            'building' => 'East Wing',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($existing->fresh())
        ->name->toBe('Room 101 Updated')
        ->capacity->toBe(50)
        ->building->toBe('East Wing');

    Livewire::test(ListRooms::class)
        ->callAction(TestAction::make('toggleStatus')->table($existing))
        ->assertNotified();

    expect($existing->fresh()?->is_active)->toBeFalse();
});
