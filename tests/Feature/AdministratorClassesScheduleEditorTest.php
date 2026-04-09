<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id'], 'activity_log_subject_index');
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->index(['causer_type', 'causer_id'], 'activity_log_causer_index');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    if (! Schema::hasTable('document_locations')) {
        Schema::create('document_locations', function (Blueprint $table) {
            $table->id();
            $table->string('picture_1x1')->nullable();
            $table->timestamps();
        });
    }
});

it('includes selected class schedules and weekly options for the administrator schedule planner', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    $primaryRoom = Room::factory()->create([
        'name' => 'Room 201',
        'is_active' => true,
    ]);

    $secondaryRoom = Room::factory()->create([
        'name' => 'Room 305',
        'is_active' => true,
    ]);

    $class = Classes::factory()->create([
        'classification' => 'college',
        'room_id' => $primaryRoom->id,
        'semester' => 1,
        'school_year' => $schoolYear,
        'section' => 'A',
    ]);

    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $primaryRoom->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:30',
    ]);

    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $secondaryRoom->id,
        'day_of_week' => 'Wednesday',
        'start_time' => '13:00',
        'end_time' => '14:30',
    ]);

    $this->get(route('administrators.classes.index', ['selected' => $class->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->where('selected_class.id', $class->id)
            ->has('selected_class.schedules', 2)
            ->where('selected_class.schedules.0.day_of_week', 'Monday')
            ->where('selected_class.schedules.0.start_time', '08:00')
            ->where('selected_class.schedules.0.end_time', '09:30')
            ->where('selected_class.schedules.0.room.id', $primaryRoom->id)
            ->where('selected_class.schedules.0.room.label', $primaryRoom->name)
            ->where('selected_class.schedules.1.day_of_week', 'Wednesday')
            ->where('selected_class.schedules.1.room.id', $secondaryRoom->id)
            ->has('options.day_of_week', 7)
            ->has('options.rooms', 2)
        );
});
