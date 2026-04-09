<?php

declare(strict_types=1);

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Faculty;
use App\Models\Student;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    // Clear any existing activities
    Activity::truncate();
});

it('logs activity when a class is created', function (): void {
    $faculty = Faculty::factory()->create();

    $class = Classes::create([
        'subject_code' => 'CS101',
        'section' => 'A1',
        'faculty_id' => $faculty->id,
        'school_year' => '2024-2025',
        'semester' => 1,
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    $activity = Activity::latest()->first();

    expect($activity)->not()->toBeNull()
        ->and($activity->log_name)->toBe('classes')
        ->and($activity->event)->toBe('created')
        ->and($activity->subject_type)->toBe(Classes::class)
        ->and($activity->subject_id)->toBe($class->id);
});

it('logs activity when a class is updated', function (): void {
    $faculty = Faculty::factory()->create();

    $class = Classes::create([
        'subject_code' => 'CS101',
        'section' => 'A1',
        'faculty_id' => $faculty->id,
        'school_year' => '2024-2025',
        'semester' => 1,
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    // Update the class
    $class->update(['section' => 'A2']);

    $activity = Activity::where('log_name', 'classes')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not()->toBeNull()
        ->and($activity->log_name)->toBe('classes')
        ->and($activity->event)->toBe('updated')
        ->and($activity->properties->has('old'))->toBeTrue()
        ->and($activity->properties->has('attributes'))->toBeTrue();
});

it('logs activity when a class post is created', function (): void {
    $faculty = Faculty::factory()->create();

    $class = Classes::create([
        'subject_code' => 'CS101',
        'section' => 'A1',
        'faculty_id' => $faculty->id,
        'school_year' => '2024-2025',
        'semester' => 1,
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    $post = ClassPost::create([
        'class_id' => $class->id,
        'title' => 'Midterm Exam Announcement',
        'content' => 'The midterm exam will be on Friday.',
        'type' => 'announcement',
    ]);

    $activity = Activity::where('log_name', 'class_posts')->latest()->first();

    expect($activity)->not()->toBeNull()
        ->and($activity->event)->toBe('created')
        ->and($activity->subject_type)->toBe(ClassPost::class)
        ->and($activity->subject_id)->toBe($post->id);
});

it('logs activity when class enrollment grades are updated', function (): void {
    $faculty = Faculty::factory()->create();
    $student = Student::factory()->create();

    $class = Classes::create([
        'subject_code' => 'CS101',
        'section' => 'A1',
        'faculty_id' => $faculty->id,
        'school_year' => '2024-2025',
        'semester' => 1,
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    $enrollment = ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    // Update grades
    $enrollment->update(['prelim_grade' => 1.5, 'midterm_grade' => 1.75]);

    $activity = Activity::where('log_name', 'class_enrollments')
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not()->toBeNull()
        ->and($activity->subject_type)->toBe(ClassEnrollment::class)
        ->and($activity->properties->get('attributes'))->toHaveKey('prelim_grade');
});

it('only logs dirty attributes when class is updated', function (): void {
    $faculty = Faculty::factory()->create();

    $class = Classes::create([
        'subject_code' => 'CS101',
        'section' => 'A1',
        'faculty_id' => $faculty->id,
        'school_year' => '2024-2025',
        'semester' => 1,
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    Activity::truncate();

    // Update only the section
    $class->update(['section' => 'B1']);

    $activity = Activity::latest()->first();

    expect($activity)->not()->toBeNull()
        ->and($activity->properties->get('attributes'))->toHaveKey('section')
        ->and($activity->properties->get('old'))->toHaveKey('section')
        ->and($activity->properties->get('attributes'))->not()->toHaveKey('maximum_slots');
});
