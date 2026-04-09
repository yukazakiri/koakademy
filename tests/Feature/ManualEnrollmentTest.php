<?php

declare(strict_types=1);

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Services\GeneralSettingsService;

beforeEach(function () {
    $this->faculty = Faculty::factory()->create();
    $this->subject = Subject::factory()->create(['code' => 'IT101']);

    // Mock settings
    $this->mock(GeneralSettingsService::class, function ($mock) {
        $mock->shouldReceive('getCurrentSchoolYearString')->andReturn('2023-2024');
        $mock->shouldReceive('getCurrentSemester')->andReturn('1st Semester');
    });

    $this->class = Classes::factory()->create([
        'faculty_id' => $this->faculty->id,
        'subject_code' => 'IT101',
        'school_year' => '2023-2024',
        'semester' => '1st Semester',
        'section' => 'A',
    ]);
});

test('faculty can search students', function () {
    $student = Student::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'student_id' => '20230001',
    ]);

    $this->actingAs($this->faculty, 'faculty')
        ->getJson(route('classes.students.search', ['class' => $this->class->id, 'query' => 'John']))
        ->assertOk()
        ->assertJsonFragment(['name' => 'John Doe'])
        ->assertJsonPath('students.0.status.in_this_class', false);
});

test('search indicates if student is already in class', function () {
    $student = Student::factory()->create(['first_name' => 'Jane']);
    ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $student->id,
    ]);

    $this->actingAs($this->faculty, 'faculty')
        ->getJson(route('classes.students.search', ['class' => $this->class->id, 'query' => 'Jane']))
        ->assertOk()
        ->assertJsonPath('students.0.status.in_this_class', true);
});

test('search indicates if student is in other section', function () {
    $student = Student::factory()->create(['first_name' => 'Bob']);
    $otherClass = Classes::factory()->create([
        'subject_code' => 'IT101',
        'school_year' => '2023-2024',
        'semester' => '1st Semester',
        'section' => 'B',
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $otherClass->id,
        'student_id' => $student->id,
    ]);

    $this->actingAs($this->faculty, 'faculty')
        ->getJson(route('classes.students.search', ['class' => $this->class->id, 'query' => 'Bob']))
        ->assertOk()
        ->assertJsonPath('students.0.status.in_other_section', 'B');
});

test('faculty can verify subject enrollment', function () {
    $student = Student::factory()->create(['first_name' => 'Alice']);
    SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $this->subject->id,
    ]);

    $this->actingAs($this->faculty, 'faculty')
        ->getJson(route('classes.students.search', ['class' => $this->class->id, 'query' => 'Alice']))
        ->assertOk()
        ->assertJsonPath('students.0.status.has_subject_enrollment', true);
});

test('faculty can enroll student', function () {
    $student = Student::factory()->create();

    $this->actingAs($this->faculty, 'faculty')
        ->post(route('classes.students.store', $this->class->id), [
            'student_id' => $student->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('class_enrollments', [
        'class_id' => $this->class->id,
        'student_id' => $student->id,
    ]);
});

test('cannot enroll student already in class', function () {
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $student->id,
    ]);

    $this->actingAs($this->faculty, 'faculty')
        ->post(route('classes.students.store', $this->class->id), [
            'student_id' => $student->id,
        ])
        ->assertSessionHas('flash.error');
});
