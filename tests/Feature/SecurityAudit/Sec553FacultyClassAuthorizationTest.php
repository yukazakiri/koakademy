<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    $this->school = School::factory()->create(['is_active' => true]);

    GeneralSetting::factory()->create([
        'school_id' => $this->school->id,
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);

    $this->room = Room::factory()->create(['school_id' => $this->school->id]);
    $this->course = Course::factory()->create(['school_id' => $this->school->id]);
    $this->subject = Subject::factory()->create([
        'school_id' => $this->school->id,
        'course_id' => $this->course->id,
        'code' => 'CS101',
    ]);

    $this->facultyA = Faculty::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'faculty.a@example.com',
        'faculty_id_number' => 'FAC-001',
    ]);
    $this->userA = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty.a@example.com',
        'faculty_id_number' => 'FAC-001',
        'record_id' => $this->facultyA->id,
        'school_id' => $this->school->id,
    ]);
    $this->userA->organizations()->syncWithoutDetaching([$this->school->id => ['is_primary' => true]]);

    $this->facultyB = Faculty::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'faculty.b@example.com',
        'faculty_id_number' => 'FAC-002',
    ]);
    $this->userB = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty.b@example.com',
        'faculty_id_number' => 'FAC-002',
        'record_id' => $this->facultyB->id,
        'school_id' => $this->school->id,
    ]);
    $this->userB->organizations()->syncWithoutDetaching([$this->school->id => ['is_primary' => true]]);

    $this->classA = Classes::factory()->create([
        'school_id' => $this->school->id,
        'faculty_id' => $this->facultyA->id,
        'room_id' => $this->room->id,
        'subject_id' => $this->subject->id,
        'subject_code' => 'CS101',
        'section' => 'A',
        'school_year' => '2026 - 2027',
        'semester' => '1',
        'maximum_slots' => 40,
    ]);

    $this->classB = Classes::factory()->create([
        'school_id' => $this->school->id,
        'faculty_id' => $this->facultyB->id,
        'room_id' => $this->room->id,
        'subject_id' => $this->subject->id,
        'subject_code' => 'CS101',
        'section' => 'B',
        'school_year' => '2026 - 2027',
        'semester' => '1',
        'maximum_slots' => 40,
    ]);
});

it('forbids another faculty member from updating someone else class', function (): void {
    $this->actingAs($this->userB)
        ->put(route('faculty.classes.update', ['class' => $this->classA->id]), [
            'room_id' => $this->room->id,
            'faculty_id' => $this->facultyB->id,
            'maximum_slots' => 5,
            'section' => 'HACKED',
            'semester' => '1',
            'school_year' => '2026 - 2027',
            'schedules' => [],
        ])
        ->assertForbidden();

    $this->classA->refresh();
    expect($this->classA->section)->toBe('A')
        ->and((string) $this->classA->faculty_id)->toBe((string) $this->facultyA->id);
});

it('allows owning faculty to update their class without reassigning faculty_id', function (): void {
    $this->actingAs($this->userA)
        ->put(route('faculty.classes.update', ['class' => $this->classA->id]), [
            'room_id' => $this->room->id,
            'faculty_id' => $this->facultyB->id,
            'maximum_slots' => 35,
            'section' => 'A2',
            'semester' => '1',
            'school_year' => '2026 - 2027',
            'schedules' => [],
        ])
        ->assertRedirect();

    $this->classA->refresh();
    expect($this->classA->section)->toBe('A2')
        ->and((string) $this->classA->faculty_id)->toBe((string) $this->facultyA->id);
});

it('blocks GET requests and unilateral student transfers without a valid signed transfer request', function (): void {
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'course_id' => $this->course->id,
    ]);

    $enrollment = ClassEnrollment::create([
        'school_id' => $this->school->id,
        'class_id' => $this->classA->id,
        'student_id' => $student->id,
    ]);

    // GET is no longer permitted (prevents CSRF via state-changing GET)
    $this->actingAs($this->userB)
        ->get('/faculty/classes/'.$this->classA->id.'/students/'.$student->id.'/move/'.$this->classB->id.'/accept')
        ->assertMethodNotAllowed();

    // POST without signed request/token is forbidden
    $this->actingAs($this->userB)
        ->post(route('faculty.classes.students.move.accept', [
            'class' => $this->classA->id,
            'student' => $student->id,
            'target_class' => $this->classB->id,
        ]))
        ->assertForbidden();

    expect((string) $enrollment->fresh()->class_id)->toBe((string) $this->classA->id);

    // Valid signed transfer request initiated by source faculty succeeds once, then cannot be replayed
    $token = 'valid-transfer-token-12345';
    Cache::put("class_transfer:{$token}", [
        'source_class_id' => (string) $this->classA->id,
        'target_class_id' => (string) $this->classB->id,
        'student_id' => (string) $student->id,
        'requested_by_faculty_id' => (string) $this->facultyA->id,
    ], now()->addHour());

    $signedUrl = URL::temporarySignedRoute('faculty.classes.students.move.accept', now()->addHour(), [
        'class' => $this->classA->id,
        'student' => $student->id,
        'target_class' => $this->classB->id,
        'token' => $token,
    ]);

    $this->actingAs($this->userB)
        ->post($signedUrl)
        ->assertRedirect(route('faculty.classes.show', $this->classB));

    expect((string) $enrollment->fresh()->class_id)->toBe((string) $this->classB->id);

    // Replay is rejected
    $this->actingAs($this->userB)
        ->post($signedUrl)
        ->assertForbidden();
});
