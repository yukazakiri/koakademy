<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
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

it('can filter classes by available slots', function () {
    // Authenticate as admin
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);
    $semester = 1;

    // Create a course for students
    $course = App\Models\Course::factory()->create();

    // Class with available slots (max 10, enrolled 0)
    $classAvailable = Classes::factory()->create([
        'maximum_slots' => 10,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
    ]);

    // Class with no available slots (max 1, enrolled 1)
    $classFull = Classes::factory()->create([
        'maximum_slots' => 1,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
    ]);

    ClassEnrollment::factory()->create([
        'class_id' => $classFull->id,
        'student_id' => Student::factory()->create([
            'course_id' => $course->id,
            'document_location_id' => null,
        ])->id,
    ]);

    // Request with available_slots=true
    $this->get(route('administrators.classes.index', ['available_slots' => true]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $classAvailable->id)
        );
});

it('can filter classes by fully enrolled', function () {
    // Authenticate as admin
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);
    $semester = 1;

    // Create a course for students
    $course = App\Models\Course::factory()->create();

    // Class with available slots
    $classAvailable = Classes::factory()->create([
        'maximum_slots' => 10,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
    ]);

    // Class fully enrolled
    $classFull = Classes::factory()->create([
        'maximum_slots' => 1,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
    ]);

    ClassEnrollment::factory()->create([
        'class_id' => $classFull->id,
        'student_id' => Student::factory()->create([
            'course_id' => $course->id,
            'document_location_id' => null,
        ])->id,
    ]);

    // Request with fully_enrolled=true
    $this->get(route('administrators.classes.index', ['fully_enrolled' => true]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $classFull->id)
        );

    // Request with fully_enrolled=false (should show available)
    $this->get(route('administrators.classes.index', ['fully_enrolled' => false]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $classAvailable->id)
        );
});

it('lists classes by most recent first by default', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);
    $semester = 1;

    $olderClass = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'created_at' => now()->subDay(),
    ]);

    $newerClass = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'created_at' => now(),
    ]);

    $this->get(route('administrators.classes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->where('classes.data.0.id', $newerClass->id)
            ->where('classes.data.1.id', $olderClass->id)
        );
});
