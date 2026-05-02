<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createFacultyClassContext(): array
{
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty-assignment@example.com',
        'faculty_id_number' => 'FAC-90001',
    ]);

    $faculty = Faculty::create([
        'id' => (string) Str::uuid(),
        'first_name' => 'Test',
        'last_name' => 'Faculty',
        'email' => $user->email,
        'password' => bcrypt('password'),
        'status' => 'active',
        'faculty_id_number' => 'FAC-90001',
    ]);

    $class = Classes::create([
        'subject_code' => 'CS201',
        'section' => 'B1',
        'faculty_id' => $faculty->id,
        'school_year' => '2025-2026',
        'semester' => 1,
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    return [$user, $faculty, $class];
}

it('requires selected students when assignment audience is specific students', function (): void {
    [$user, $faculty, $class] = createFacultyClassContext();

    $payload = [
        'title' => 'Structured Assignment',
        'content' => 'Read the brief and submit your response.',
        'instruction' => 'Answer all sections clearly.',
        'type' => 'assignment',
        'status' => 'backlog',
        'priority' => 'medium',
        'start_date' => '2026-05-02',
        'due_date' => '2026-05-05',
        'progress_percent' => 0,
        'total_points' => 100,
        'assigned_faculty_id' => $faculty->id,
        'audience_mode' => 'specific_students',
        'assigned_student_ids' => [],
        'rubric' => [
            [
                'title' => 'Quiz Criteria',
                'description' => 'Must answer every item.',
                'points' => 100,
                'levels' => [
                    ['title' => 'Passed', 'description' => 'Passed threshold reached'],
                    ['title' => 'Failed', 'description' => 'Below threshold'],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($user)->post("/faculty/classes/{$class->id}/posts", $payload);

    $response
        ->assertSessionHasErrors(['assigned_student_ids'])
        ->assertRedirect();
});

it('stores assignment audience, instruction, rubric, and file attachments', function (): void {
    Storage::fake();

    [$user, $faculty, $class] = createFacultyClassContext();

    $student = Student::factory()->create();
    $enrollment = ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    $response = $this->actingAs($user)->post("/faculty/classes/{$class->id}/posts", [
        'title' => 'Unit 3 Assignment',
        'content' => 'Complete the attached worksheet.',
        'instruction' => 'Show full solution steps for each problem.',
        'type' => 'assignment',
        'status' => 'in_progress',
        'priority' => 'high',
        'start_date' => '2026-05-02',
        'due_date' => '2026-05-07',
        'progress_percent' => 25,
        'total_points' => 50,
        'assigned_faculty_id' => $faculty->id,
        'audience_mode' => 'specific_students',
        'assigned_student_ids' => [$enrollment->id],
        'rubric' => [
            [
                'title' => 'Quiz Criteria',
                'description' => 'Correctness and clarity',
                'points' => 50,
                'levels' => [
                    ['title' => 'Passed', 'description' => 'Meets expected quality'],
                    ['title' => 'Failed', 'description' => 'Needs revision'],
                ],
            ],
        ],
        'files' => [UploadedFile::fake()->create('worksheet.pdf', 256, 'application/pdf')],
    ]);

    $response->assertRedirect();

    $post = ClassPost::query()->latest('id')->first();

    expect($post)->not->toBeNull()
        ->and($post?->title)->toBe('Unit 3 Assignment')
        ->and($post?->type->value ?? $post?->type)->toBe('assignment')
        ->and($post?->instruction)->toBe('Show full solution steps for each problem.')
        ->and($post?->audience_mode)->toBe('specific_students')
        ->and($post?->assigned_student_ids)->toBe([(int) $enrollment->id])
        ->and($post?->rubric)->toBeArray()
        ->and($post?->rubric[0]['title'])->toBe('Quiz Criteria')
        ->and($post?->rubric[0]['levels'][0]['title'])->toBe('Passed')
        ->and($post?->attachments)->toBeArray()
        ->and($post?->attachments)->toHaveCount(1)
        ->and($post?->attachments[0]['kind'])->toBe('file')
        ->and($post?->attachments[0]['url'])->toContain('/storage/class-post-attachments/');
});
