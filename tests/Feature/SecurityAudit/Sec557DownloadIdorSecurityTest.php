<?php

declare(strict_types=1);

use App\Enums\ClassPostType;
use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');

    $this->school = School::factory()->create(['is_active' => true]);
    GeneralSetting::factory()->create([
        'school_id' => $this->school->id,
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
});

it('blocks cross-class IDOR when downloading class post attachments not belonging to the requested class', function (): void {
    $room = Room::factory()->create(['school_id' => $this->school->id]);
    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $subject = Subject::factory()->create(['school_id' => $this->school->id, 'course_id' => $course->id]);
    $faculty = Faculty::factory()->create(['school_id' => $this->school->id]);

    $classA = Classes::factory()->create([
        'school_id' => $this->school->id,
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
        'subject_id' => $subject->id,
    ]);
    $classB = Classes::factory()->create([
        'school_id' => $this->school->id,
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
        'subject_id' => $subject->id,
    ]);

    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'student.classa@example.com',
        'school_id' => $this->school->id,
    ]);
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'course_id' => $course->id,
        'user_id' => $studentUser->id,
        'email' => $studentUser->email,
    ]);
    ClassEnrollment::create([
        'school_id' => $this->school->id,
        'class_id' => $classA->id,
        'student_id' => $student->id,
    ]);

    Storage::disk('private')->put('class-post-attachments/class-b-exam.pdf', '%PDF-secret-exam');
    ClassPost::create([
        'class_id' => $classB->id,
        'title' => 'Class B Exam',
        'content' => 'Confidential',
        'type' => ClassPostType::Announcement,
        'attachments' => [
            ['path' => 'class-post-attachments/class-b-exam.pdf', 'name' => 'exam.pdf'],
        ],
    ]);

    // Student enrolled only in classA must NOT download classB's attachment via classA's route
    $this->actingAs($studentUser)
        ->get(route('classes.posts.attachments.download', ['class' => $classA->id, 'file' => 'class-b-exam.pdf']))
        ->assertNotFound();
});

it('blocks cross-user IDOR on AI document downloads and allows only the owning user', function (): void {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => $this->school->id]);
    $student = User::factory()->create(['role' => UserRole::Student, 'school_id' => $this->school->id]);

    $docId = 'doc_owner_scoped_1234';
    Cache::put("ai:doc:{$docId}", [
        'id' => $docId,
        'user_id' => $admin->id,
        'school_id' => $this->school->id,
        'title' => 'Financial Audit',
        'filename' => 'Financial_Audit.md',
        'format' => 'markdown',
        'content' => '# Confidential Audit',
    ], now()->addHour());

    $this->actingAs($student)
        ->get(route('ai.download-document', ['documentId' => $docId]))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('ai.download-document', ['documentId' => $docId]))
        ->assertSuccessful()
        ->assertSee('# Confidential Audit');
});

it('isolates user-scoped student list and timetable exports and rejects GET on /download/schedule', function (): void {
    $facultyA = User::factory()->create(['role' => UserRole::Instructor, 'school_id' => $this->school->id]);
    $facultyB = User::factory()->create(['role' => UserRole::Instructor, 'school_id' => $this->school->id]);

    Storage::disk('private')->put("exports/student-lists/{$facultyA->id}/private-roster.pdf", '%PDF-A');
    Storage::disk('private')->put("schedules/{$facultyA->id}/private-timetable.pdf", '%PDF-TT-A');

    $this->actingAs($facultyB)
        ->get('/download/student-list/private-roster.pdf')
        ->assertNotFound();

    $this->actingAs($facultyB)
        ->get('/download/timetable-pdf/private-timetable.pdf')
        ->assertNotFound()
        ->assertJsonMissing(['path', 'disk']);

    $this->actingAs($facultyA)
        ->get('/download/student-list/private-roster.pdf')
        ->assertSuccessful();

    $this->actingAs($facultyA)
        ->get('/download/schedule?type=timetable')
        ->assertMethodNotAllowed();
});
