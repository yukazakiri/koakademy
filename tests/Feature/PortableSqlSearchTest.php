<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Classes\ClassesResource;
use App\Filament\Resources\Classes\Pages\ListClasses;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
    ]);

    $this->user = User::factory()->create(['role' => UserRole::Admin]);
    Permission::findOrCreate('ViewAny:Classes', 'web');
    $this->user->givePermissionTo('ViewAny:Classes');

    actingAs($this->user);
    Filament::setCurrentPanel('admin');
});

it('finds a class through its JSON subject array in the global search', function (): void {
    $subject = Subject::factory()->create([
        'code' => 'MATH 101',
        'title' => 'College Algebra',
    ]);

    $class = Classes::factory()->create([
        'subject_id' => null,
        'subject_code' => 'MATH 101',
        'subject_ids' => [$subject->id],
        'section' => 'A',
        'classification' => 'college',
        'school_year' => '2024 - 2025',
        'semester' => 2,
    ]);

    request()->merge(['search' => 'College Algebra']);

    expect(ClassesResource::getGlobalSearchEloquentQuery()->pluck('id')->all())
        ->toContain($class->id);
});

it('filters the classes table by a course stored in the JSON subject array', function (): void {
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();

    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $otherSubject = Subject::factory()->create(['course_id' => $otherCourse->id]);

    $matchingClass = Classes::factory()->create([
        'subject_id' => null,
        'subject_code' => $subject->code,
        'subject_ids' => [$subject->id],
        'section' => 'A',
        'classification' => 'college',
        'school_year' => '2024 - 2025',
        'semester' => 2,
    ]);

    $otherClass = Classes::factory()->create([
        'subject_id' => null,
        'subject_code' => $otherSubject->code,
        'subject_ids' => [$otherSubject->id],
        'section' => 'B',
        'classification' => 'college',
        'school_year' => '2024 - 2025',
        'semester' => 2,
    ]);

    Livewire::test(ListClasses::class)
        ->filterTable('course', $course->id)
        ->assertCanSeeTableRecords([$matchingClass])
        ->assertCanNotSeeTableRecords([$otherClass]);
});

it('searches the enrollment and class feed APIs on sqlite', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'first_name' => 'Alicia',
        'last_name' => 'Mercado',
        'course_id' => $course->id,
    ]);
    $class = Classes::factory()->create([
        'classification' => 'college',
        'school_year' => '2024 - 2025',
        'semester' => 2,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 2,
        'status' => 'enrolled',
    ]);

    $classEnrollment = ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'class_id' => $class->id,
    ]);

    $post = ClassPost::create([
        'class_id' => $class->id,
        'title' => 'Scholarship Orientation',
        'content' => 'Important details for graduating students.',
        'type' => 'announcement',
    ]);

    getJson(route('api.enrollments.index', ['search' => 'alicia']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $enrollment->id);

    getJson(route('api.class-enrollments.index', ['search' => 'alicia']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $classEnrollment->id);

    getJson(route('api.class-posts.index', ['search' => 'scholarship']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $post->id);
});
