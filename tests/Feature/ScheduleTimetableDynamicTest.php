<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Timetable;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

final class ScheduleTimetableDynamicTest extends TestCase
{
    public function test_initial_state_has_no_view_selected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        // Create general settings for proper initialization
        GeneralSetting::factory()->create([
            'semester' => 1,
            'school_starting_date' => '2024-08-15',
            'school_ending_date' => '2025-05-30',
        ]);

        Livewire::test(Timetable::class)
            ->assertSet('selectedView', null)
            ->assertSet('selectedCourse', null)
            ->assertSet('selectedRoom', null)
            ->assertSet('selectedYearLevel', null);
    }

    public function test_selecting_room_view_shows_room_dropdown(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        Livewire::test(Timetable::class)
            ->set('selectedView', 'room')
            ->assertSet('selectedView', 'room')
            ->call('loadScheduleData')
            ->assertOk();
    }

    public function test_selecting_course_view_shows_course_dropdown(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        Livewire::test(Timetable::class)
            ->set('selectedView', 'course')
            ->assertSet('selectedView', 'course')
            ->call('loadScheduleData')
            ->assertOk();
    }

    public function test_selecting_year_level_view_shows_year_level_dropdown(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        Livewire::test(Timetable::class)
            ->set('selectedView', 'year_level')
            ->assertSet('selectedView', 'year_level')
            ->call('loadScheduleData')
            ->assertOk();
    }

    public function test_room_selection_triggers_data_loading(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        // Create a room for testing
        $room = Room::factory()->create(['name' => 'Test Room 101']);

        Livewire::test(Timetable::class)
            ->set('selectedView', 'room')
            ->set('selectedRoom', (string) $room->id)
            ->assertSet('selectedRoom', (string) $room->id)
            ->call('loadScheduleData')
            ->assertOk();
    }

    public function test_course_selection_triggers_data_loading(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        // Create a course for testing
        $course = Course::factory()->create([
            'code' => 'BSCS',
            'title' => 'Bachelor of Science in Computer Science',
            'is_active' => true,
        ]);

        Livewire::test(Timetable::class)
            ->set('selectedView', 'course')
            ->set('selectedCourse', (string) $course->id)
            ->assertSet('selectedCourse', (string) $course->id)
            ->call('loadScheduleData')
            ->assertOk();
    }

    public function test_view_description_changes_based_on_selection(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        $component = Livewire::test(Timetable::class);

        // Test room view description
        $component->set('selectedView', 'room');
        expect($component->instance()->getViewDescription())
            ->toBe('Select a room to see all its scheduled classes');

        // Test course view description
        $component->set('selectedView', 'course');
        expect($component->instance()->getViewDescription())
            ->toBe('Select a course to see its schedule');

        // Test year level view description
        $component->set('selectedView', 'year_level');
        expect($component->instance()->getViewDescription())
            ->toBe('Select a year level to see all its classes');
    }

    public function test_has_year_level_sections_works_correctly(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();
        $course = Course::factory()->create(['is_active' => true]);

        $component = Livewire::test(Timetable::class);

        // Should be false initially
        expect($component->instance()->hasYearLevelSections())->toBeFalse();

        // Should be false when no course selected
        $component->set('selectedView', 'course');
        expect($component->instance()->hasYearLevelSections())->toBeFalse();

        // Should be false when specific year level selected
        $component->set('selectedCourse', (string) $course->id)
            ->set('selectedYearLevel', '1');
        expect($component->instance()->hasYearLevelSections())->toBeFalse();
    }

    public function test_has_data_to_show_works_correctly(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        $component = Livewire::test(Timetable::class);

        // Initially should be false
        expect($component->instance()->hasDataToShow())->toBeFalse();
    }

    public function test_refresh_schedule_data_method_exists(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        Livewire::test(Timetable::class)
            ->call('refreshScheduleData')
            ->assertOk();
    }

    public function test_reset_filters_clears_all_selections(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        $course = Course::factory()->create(['is_active' => true]);
        $room = Room::factory()->create();

        $component = Livewire::test(Timetable::class);

        // Set some values
        $component->set('selectedCourse', (string) $course->id)
            ->set('selectedRoom', (string) $room->id)
            ->set('selectedYearLevel', '2');

        // Call reset filters
        $component->call('resetFilters');

        // Check that values are cleared
        $component->assertSet('selectedCourse', null)
            ->assertSet('selectedRoom', null)
            ->assertSet('selectedYearLevel', null);
    }

    public function test_academic_year_options_uses_general_settings_service(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        $component = Livewire::test(Timetable::class);

        $options = $component->instance()->getAcademicYearOptions();

        expect($options)->toBeArray();
        expect(count($options))->toBeGreaterThan(0);

        // Should contain the format "YYYY - YYYY"
        foreach ($options as $key => $value) {
            expect($value)->toMatch('/\d{4} - \d{4}/');
        }
    }

    public function test_course_options_only_shows_active_courses(): void
    {
        $user = User::factory()->create(['role' => UserRole::Registrar]);
        $this->actingAs($user);

        GeneralSetting::factory()->create();

        // Create active and inactive courses
        $activeCourse = Course::factory()->create([
            'code' => 'BSCS',
            'title' => 'Computer Science',
            'is_active' => true,
        ]);

        $inactiveCourse = Course::factory()->create([
            'code' => 'BSIT',
            'title' => 'Information Technology',
            'is_active' => false,
        ]);

        $component = Livewire::test(Timetable::class);
        $options = $component->instance()->getCourseOptions();

        expect($options)->toBeArray();
        expect($options)->toHaveKey((string) $activeCourse->id);
        expect($options)->not->toHaveKey((string) $inactiveCourse->id);
    }
}
