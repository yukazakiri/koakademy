<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\GeneralSettingsService;
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

it('lists classes with multiple subjects on the administrator index', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $subject = Subject::factory()->create([
        'code' => 'GE-TEST',
        'title' => 'General Education Test Subject',
    ]);

    $period = app(GeneralSettingsService::class);
    $class = Classes::factory()->create([
        'subject_id' => null,
        'subject_code' => 'GE-TEST',
        'subject_ids' => [$subject->id],
        'school_year' => $period->getCurrentSchoolYearString(),
        'semester' => $period->getCurrentSemester(),
        'classification' => 'college',
    ]);

    $this->get(route('administrators.classes.index', ['search' => 'GE-TEST']))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $class->id)
            ->where('classes.data.0.subject_code', 'GE-TEST')
        );
});

it('supports partial reloads for classes table updates', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $manifest = public_path('build/manifest.json');
    $inertiaVersion = config('app.asset_url')
        ? hash('xxh128', (string) config('app.asset_url'))
        : (file_exists($manifest) ? hash_file('xxh128', $manifest) : '');

    $this->actingAs($user)
        ->get(route('administrators.classes.index', ['search' => 'math']), [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'administrators/classes/index',
            'X-Inertia-Partial-Data' => 'classes,filters',
            'X-Inertia-Version' => $inertiaVersion,
        ])
        ->assertSuccessful()
        ->assertJsonPath('component', 'administrators/classes/index')
        ->assertJsonStructure([
            'props' => [
                'classes' => ['data', 'meta'],
                'filters',
            ],
        ])
        ->assertJsonMissingPath('props.options')
        ->assertJsonMissingPath('props.defaults')
        ->assertJsonMissingPath('props.selected_class');
});

it('can filter classes by available slots', function () {
    // Authenticate as admin
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);
    $semester = 1;

    // Create a course for students
    $course = Course::factory()->create();

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
    $course = Course::factory()->create();

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

    // No enrollment-status filter should show both full and available classes.
    $this->get(route('administrators.classes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 2)
            ->where('filters.fully_enrolled', null)
        );

    // Empty query values should also be treated as a cleared filter.
    $this->get(route('administrators.classes.index', ['fully_enrolled' => '']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 2)
            ->where('filters.fully_enrolled', null)
        );

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

it('sorts classes in the database rather than in the response', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    $zeta = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'subject_code' => 'ZETA 1',
        'section' => 'A',
    ]);

    $alpha = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'subject_code' => 'ALPHA 1',
        'section' => 'A',
    ]);

    $this->get(route('administrators.classes.index', ['sort' => 'record_title', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->where('classes.data.0.id', $alpha->id)
            ->where('classes.data.1.id', $zeta->id)
            ->where('filters.sort', 'record_title')
            ->where('filters.direction', 'asc')
        );
});

it('paginates the class list on the server', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    Classes::factory()->count(12)->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
    ]);

    $this->get(route('administrators.classes.index', ['per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 10)
            ->where('classes.meta.total', 12)
            ->where('classes.meta.per_page', 10)
            ->where('classes.meta.current_page', 1)
            ->where('classes.meta.last_page', 2)
        );

    $this->get(route('administrators.classes.index', ['per_page' => 10, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 2)
            ->where('classes.meta.current_page', 2)
            ->where('classes.meta.from', 11)
            ->where('classes.meta.to', 12)
        );
});

it('matches a search term against the program code of a class', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    $course = Course::factory()->create(['code' => 'BSBA', 'curriculum_year' => '2024 - 2025']);
    $subject = Subject::factory()->create(['code' => 'BSBA 1', 'title' => 'Business Mathematics']);

    $matching = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => 'BSBA 1',
    ]);

    Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'course_codes' => [],
        'subject_ids' => [],
        'subject_code' => 'IT 1',
    ]);

    $this->get(route('administrators.classes.index', ['search' => 'BSBA']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $matching->id)
        );
});

it('filters classes by the program they belong to', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    $bsba = Course::factory()->create(['code' => 'BSBA']);
    $it = Course::factory()->create(['code' => 'IT']);

    $bsbaClass = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'course_codes' => [$bsba->id],
    ]);

    Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'course_codes' => [$it->id],
    ]);

    $this->get(route('administrators.classes.index', ['course_id' => $bsba->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $bsbaClass->id)
        );
});

it('matches a search term against the faculty of a class', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    $faculty = Faculty::factory()->create([
        'first_name' => 'Roderick',
        'last_name' => 'Santos',
        'full_name' => 'Santos, Roderick',
    ]);

    $matching = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'faculty_id' => $faculty->id,
        'subject_code' => 'FINMAN 6',
    ]);

    Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'faculty_id' => null,
        'subject_code' => 'IT 1',
    ]);

    $this->get(route('administrators.classes.index', ['search' => 'Santos']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $matching->id)
        );
});

it('matches a search term against the record title of a class', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    $matching = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'subject_code' => 'FINMAN 6',
        'section' => 'A',
    ]);

    Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'subject_code' => 'TAX 2',
        'section' => 'B',
    ]);

    $this->get(route('administrators.classes.index', ['search' => 'FINMAN 6 - A']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $matching->id)
        );
});
