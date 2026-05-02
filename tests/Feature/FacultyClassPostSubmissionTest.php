<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\ClassPostSubmission;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Str;

uses()->group('class-posts');

function createSubmissionTestContext(): array
{
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty-submission@example.com',
        'faculty_id_number' => 'FAC-SUB-001',
    ]);

    $faculty = Faculty::create([
        'id' => (string) Str::uuid(),
        'first_name' => 'Test',
        'last_name' => 'Faculty',
        'email' => $user->email,
        'password' => bcrypt('password'),
        'status' => 'active',
        'faculty_id_number' => 'FAC-SUB-001',
    ]);

    $class = Classes::create([
        'subject_code' => 'CS301',
        'section' => 'A1',
        'faculty_id' => $faculty->id,
        'school_year' => '2025-2026',
        'semester' => 1,
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    return [$user, $faculty, $class];
}

it('updates assignment status', function (): void {
    [$user, $faculty, $class] = createSubmissionTestContext();
    $this->actingAs($user);

    $post = new ClassPost([
        'class_id' => $class->id,
        'title' => 'Test Assignment',
        'type' => 'assignment',
    ]);
    $post->save();

    $response = $this->patch("/faculty/classes/{$class->id}/posts/{$post->id}/status", [
        'status' => 'in_progress',
    ]);

    $response->assertRedirect();

    $post->refresh();
    expect($post->status)->toBe('in_progress');
});

it('validates grade points do not exceed total', function (): void {
    [$user, $faculty, $class] = createSubmissionTestContext();
    $this->actingAs($user);

    $post = new ClassPost([
        'class_id' => $class->id,
        'title' => 'Test Assignment',
        'type' => 'assignment',
        'total_points' => 100,
    ]);
    $post->save();

    $school = School::factory()->create();
    $student = Student::factory()->create([
        'school_id' => $school->id,
    ]);

    $enrollment = new ClassEnrollment([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);
    $enrollment->save();

    $submission = ClassPostSubmission::create([
        'class_post_id' => $post->id,
        'student_id' => $student->id,
        'status' => 'submitted',
    ]);

    $response = $this->post("/faculty/classes/{$class->id}/posts/{$post->id}/submissions/{$submission->id}/grade", [
        'points' => 150,
    ]);

    $response->assertSessionHasErrors('points');
});
