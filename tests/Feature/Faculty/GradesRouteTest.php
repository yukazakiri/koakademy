<?php

declare(strict_types=1);

namespace Tests\Feature\Faculty;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GradesRouteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Faculty $faculty;

    protected Classes $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => \App\Enums\UserRole::Instructor,
            'faculty_id_number' => 'FAC-12345',
        ]);
        $this->faculty = Faculty::factory()->create(['email' => $this->user->email]);
        $this->class = Classes::factory()->create(['faculty_id' => $this->faculty->id]);
    }

    public function test_can_save_grades()
    {
        $student = Student::factory()->create();
        $enrollment = ClassEnrollment::factory()->create([
            'class_id' => $this->class->id,
            'student_id' => $student->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('faculty.classes.grades.update', $this->class), [
                'grades' => [
                    [
                        'enrollment_id' => $enrollment->id,
                        'prelim' => 85,
                        'midterm' => 88,
                        'final' => 90,
                        'average' => 87.67,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('class_enrollments', [
            'id' => $enrollment->id,
            'prelim_grade' => 85,
            'midterm_grade' => 88,
            'finals_grade' => 90,
            'total_average' => 87.67,
        ]);
    }

    public function test_can_submit_term_grades()
    {
        // This functionality might vary depending on implementation (e.g., locking grades),
        // but checking the route exists and processes request is a good start.
        $response = $this->actingAs($this->user)
            ->post(route('faculty.classes.grades.submit', $this->class), [
                'term' => 'prelim',
            ]);

        $response->assertRedirect();
    }
}
