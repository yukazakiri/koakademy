<?php

declare(strict_types=1);

namespace Tests\Feature\Faculty;

use App\Models\ClassAttendanceRecord;
use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AttendanceRouteTest extends TestCase
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

    public function test_can_create_attendance_session()
    {
        $schedule = $this->class->schedules()->create([
            'day_of_week' => now()->format('l'),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room_id' => \App\Models\Room::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('faculty.classes.attendance.sessions.store', $this->class), [
                'session_date' => now()->toDateString(),
                'schedule_id' => $schedule->id,
                'topic' => 'Test Session',
                'default_status' => 'present',
                'mark_all' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('class_attendance_sessions', [
            'class_id' => $this->class->id,
            'topic' => 'Test Session',
        ]);
    }

    public function test_can_update_attendance_record()
    {
        $session = ClassAttendanceSession::factory()->create([
            'class_id' => $this->class->id,
            'taken_by' => $this->faculty->id,
        ]);

        $student = Student::factory()->create();
        $enrollment = ClassEnrollment::factory()->create([
            'class_id' => $this->class->id,
            'student_id' => $student->id,
        ]);

        $record = ClassAttendanceRecord::factory()->create([
            'class_attendance_session_id' => $session->id,
            'class_enrollment_id' => $enrollment->id,
            'status' => 'absent',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('faculty.classes.attendance.records.update', ['class' => $this->class, 'session' => $session]), [
                'records' => [
                    [
                        'class_enrollment_id' => $enrollment->id,
                        'status' => 'present',
                        'remarks' => 'Late arrival',
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('class_attendance_records', [
            'id' => $record->id,
            'status' => 'present',
            'remarks' => 'Late arrival',
        ]);
    }

    public function test_can_delete_attendance_session()
    {
        $session = ClassAttendanceSession::factory()->create([
            'class_id' => $this->class->id,
            'taken_by' => $this->faculty->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('faculty.classes.attendance.sessions.destroy', ['class' => $this->class, 'session' => $session]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('class_attendance_sessions', ['id' => $session->id]);
    }

    public function test_can_export_attendance_as_excel()
    {
        $response = $this->actingAs($this->user)
            ->get(route('faculty.classes.attendance.export', ['class' => $this->class, 'format' => 'excel']));

        $response->assertStatus(200);
        // Header check might fail due to case sensitivity or charset spacing, just check text/csv
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }
}
