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

final class PeopleRouteTest extends TestCase
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

    public function test_can_search_students()
    {
        // Ensure student matches class classification (default factory is College)
        $student = Student::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'student_type' => \App\Enums\StudentType::College,
        ]);

        // Ensure class is college
        $this->class->update(['classification' => 'college']);

        $response = $this->actingAs($this->user)
            ->get(route('faculty.classes.students.search', ['class' => $this->class, 'query' => 'John']));

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $student->full_name]);
    }

    public function test_can_add_student_to_class()
    {
        $student = Student::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('faculty.classes.students.store', $this->class), [
                'student_id' => $student->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('class_enrollments', [
            'class_id' => $this->class->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_can_remove_student_from_class()
    {
        $student = Student::factory()->create();
        ClassEnrollment::factory()->create([
            'class_id' => $this->class->id,
            'student_id' => $student->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('faculty.classes.students.destroy', ['class' => $this->class, 'student' => $student->id]));

        $response->assertRedirect();
        $this->assertSoftDeleted('class_enrollments', [
            'class_id' => $this->class->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_can_export_student_list_as_csv()
    {
        $response = $this->actingAs($this->user)
            ->get(route('faculty.classes.students.export', ['class' => $this->class, 'format' => 'excel']));

        $response->assertStatus(200);
        // Header check might fail due to case sensitivity or charset spacing, just check text/csv
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }
}
