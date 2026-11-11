<?php

declare(strict_types=1);

use App\Enums\ClassPostType;
use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\HelpTicket;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    Storage::fake('private');
    Storage::fake('public');
});

it('rejects html and svg attachments and accepts allowed file on help tickets', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);

    // Attempt HTML upload
    $htmlFile = UploadedFile::fake()->create('exploit.html', 10, 'text/html');

    actingAs($student)
        ->post(route('student.help.store'), [
            'type' => 'issue',
            'subject' => 'Malicious upload',
            'message' => 'Testing html upload',
            'priority' => 'high',
            'attachments' => [$htmlFile],
        ])
        ->assertSessionHasErrors(['attachments.0']);

    // Attempt SVG upload
    $svgFile = UploadedFile::fake()->create('exploit.svg', 10, 'image/svg+xml');

    actingAs($student)
        ->post(route('student.help.store'), [
            'type' => 'issue',
            'subject' => 'Malicious svg',
            'message' => 'Testing svg upload',
            'priority' => 'high',
            'attachments' => [$svgFile],
        ])
        ->assertSessionHasErrors(['attachments.0']);

    // Valid PDF upload
    $pdfFile = UploadedFile::fake()->create('document.pdf', 50, 'application/pdf');

    actingAs($student)
        ->post(route('student.help.store'), [
            'type' => 'support',
            'subject' => 'Valid upload',
            'message' => 'Here is my document',
            'priority' => 'low',
            'attachments' => [$pdfFile],
        ])
        ->assertRedirect();

    $ticket = HelpTicket::where('subject', 'Valid upload')->firstOrFail();
    expect($ticket->attachments)->toHaveCount(1);

    $attachment = $ticket->attachments[0];
    expect($attachment['name'])->toBe('document.pdf')
        ->and($attachment['url'])->not->toContain('/storage/')
        ->and($attachment['url'])->toContain('/help-tickets/'.$ticket->id.'/attachments/');

    Storage::disk('private')->assertExists($attachment['path']);
    Storage::disk('public')->assertMissing($attachment['path']);

    // Authorized owner download forces Content-Disposition attachment and X-Content-Type-Options nosniff
    $filename = basename($attachment['path']);
    actingAs($student)
        ->get(route('help-tickets.attachments.download', ['ticket' => $ticket->id, 'filename' => $filename]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=document.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // Unauthorized user cannot download ticket attachment
    $otherStudent = User::factory()->create(['role' => UserRole::Student]);
    actingAs($otherStudent)
        ->get(route('help-tickets.attachments.download', ['ticket' => $ticket->id, 'filename' => $filename]))
        ->assertForbidden();

    // Admin can download ticket attachment
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    actingAs($admin)
        ->get(route('help-tickets.attachments.download', ['ticket' => $ticket->id, 'filename' => $filename]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=document.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('rejects html attachments in administrator help ticket replies and stores securely', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $ticket = HelpTicket::create([
        'user_id' => $student->id,
        'type' => 'support',
        'subject' => 'Question',
        'message' => 'Need help',
        'priority' => 'medium',
    ]);

    $htmlFile = UploadedFile::fake()->create('payload.html', 10, 'text/html');

    actingAs($admin)
        ->post(route('administrators.help-tickets.reply', $ticket->id), [
            'message' => 'Admin reply with html',
            'attachments' => [$htmlFile],
        ])
        ->assertSessionHasErrors(['attachments.0']);

    $pngFile = UploadedFile::fake()->image('screenshot.png');

    actingAs($admin)
        ->post(route('administrators.help-tickets.reply', $ticket->id), [
            'message' => 'Admin reply with image',
            'attachments' => [$pngFile],
        ])
        ->assertRedirect();

    $reply = $ticket->replies()->latest('id')->firstOrFail();
    expect($reply->attachments)->toHaveCount(1);

    $attachment = $reply->attachments[0];
    Storage::disk('private')->assertExists($attachment['path']);
    Storage::disk('public')->assertMissing($attachment['path']);

    $filename = basename($attachment['path']);
    actingAs($student)
        ->get(route('help-tickets.attachments.download', ['ticket' => $ticket->id, 'filename' => $filename]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'attachment; filename=screenshot.png');
});

it('validates MIME types and secures fixed student documents on private storage', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    // HTML rejected for fixed document
    $htmlFile = UploadedFile::fake()->create('pwn.html', 20, 'text/html');
    actingAs($admin)
        ->post(route('administrators.students.documents.fixed.update', $student->id), [
            'document_type' => 'birth_certificate',
            'file' => $htmlFile,
        ])
        ->assertSessionHasErrors(['file']);

    // PDF rejected for picture_1x1 (only images allowed)
    $pdfFile = UploadedFile::fake()->create('not_picture.pdf', 20, 'application/pdf');
    actingAs($admin)
        ->post(route('administrators.students.documents.fixed.update', $student->id), [
            'document_type' => 'picture_1x1',
            'file' => $pdfFile,
        ])
        ->assertSessionHasErrors(['file']);

    // Valid PDF for birth certificate
    $validPdf = UploadedFile::fake()->create('birth_certificate.pdf', 50, 'application/pdf');
    actingAs($admin)
        ->post(route('administrators.students.documents.fixed.update', $student->id), [
            'document_type' => 'birth_certificate',
            'file' => $validPdf,
        ])
        ->assertRedirect();

    $student->refresh();
    $path = $student->DocumentLocation->birth_certificate;
    expect($path)->not->toBeNull();

    Storage::disk('private')->assertExists($path);
    Storage::disk('public')->assertMissing($path);

    // Download route enforces attachment and nosniff
    actingAs($admin)
        ->get(route('administrators.students.documents.fixed.show', [
            'student' => $student->id,
            'documentType' => 'birth_certificate',
        ]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', "attachment; filename={$student->student_id}_birth_certificate.pdf");
});

it('validates MIME types and secures dynamic student documents on private storage', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    // SVG rejected
    $svgFile = UploadedFile::fake()->create('exploit.svg', 10, 'image/svg+xml');
    actingAs($admin)
        ->post(route('administrators.students.documents.dynamic.store', $student->id), [
            'type' => 'Medical Exam',
            'file' => $svgFile,
        ])
        ->assertSessionHasErrors(['file']);

    // Valid docx accepted
    $docxFile = UploadedFile::fake()->create('medical.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    actingAs($admin)
        ->post(route('administrators.students.documents.dynamic.store', $student->id), [
            'type' => 'Medical Certificate',
            'file' => $docxFile,
        ])
        ->assertRedirect();

    $resource = $student->resources()->latest('id')->firstOrFail();
    expect($resource->disk)->toBe('private');

    Storage::disk('private')->assertExists($resource->file_path);
    Storage::disk('public')->assertMissing($resource->file_path);

    actingAs($admin)
        ->get(route('administrators.students.documents.dynamic.show', [
            'student' => $student->id,
            'resource' => $resource->id,
        ]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'attachment; filename=medical.docx');
});

it('rejects html in class post submissions and stores files privately with secure download route', function (): void {
    $school = School::factory()->create();
    $department = Department::factory()->create(['school_id' => $school->id]);
    $course = Course::factory()->create(['department_id' => $department->id]);

    $facultyUser = User::factory()->create([
        'role' => UserRole::Professor,
        'faculty_id_number' => 'FAC-12345',
    ]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'email' => $facultyUser->email,
        'faculty_id_number' => 'FAC-12345',
    ]);

    $studentUser = User::factory()->create(['role' => UserRole::Student]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'email' => $studentUser->email,
    ]);

    $subject = Subject::factory()->create(['school_id' => $school->id]);
    $class = Classes::factory()->create([
        'school_id' => $school->id,
        'faculty_id' => $faculty->id,
        'subject_id' => $subject->id,
    ]);

    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    // Faculty attempting to attach HTML in class post
    $htmlFile = UploadedFile::fake()->create('attack.html', 10, 'text/html');

    actingAs($facultyUser)
        ->post("/faculty/classes/{$class->id}/posts", [
            'title' => 'Post with attack',
            'type' => 'announcement',
            'files' => [$htmlFile],
        ])
        ->assertSessionHasErrors(['files.0']);

    // Faculty valid PDF upload
    $validPdf = UploadedFile::fake()->create('lecture.pdf', 30, 'application/pdf');

    actingAs($facultyUser)
        ->post("/faculty/classes/{$class->id}/posts", [
            'title' => 'Lecture Post',
            'type' => 'assignment',
            'instruction' => 'Follow guidelines',
            'audience_mode' => 'all_students',
            'total_points' => 100,
            'files' => [$validPdf],
        ])
        ->assertRedirect();

    $post = ClassPost::where('title', 'Lecture Post')->firstOrFail();
    expect($post->attachments)->toHaveCount(1);
    $attachment = $post->attachments[0];

    expect($attachment['url'])->not->toContain('/storage/')
        ->and($attachment['url'])->toContain('/classes/'.$class->id.'/posts/attachments/');

    $filename = basename($attachment['path']);
    Storage::disk('private')->assertExists($attachment['path']);
    Storage::disk('public')->assertMissing($attachment['path']);

    // Enrolled student can download post attachment with Content-Disposition attachment and X-Content-Type-Options nosniff
    actingAs($studentUser)
        ->get(route('classes.posts.attachments.download', ['class' => $class->id, 'file' => $filename]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=lecture.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    Laravel\Pennant\Feature::activateForEveryone(App\Features\Toggles\StudentClasses::class);

    // Student submitting assignment: HTML rejected
    actingAs($studentUser)
        ->post("/student/classes/{$class->id}/posts/{$post->id}/submit", [
            'content' => 'My submission',
            'files' => [$htmlFile],
        ])
        ->assertSessionHasErrors(['files.0']);

    // Student submitting valid zip
    $zipFile = UploadedFile::fake()->create('solution.zip', 50, 'application/zip');
    actingAs($studentUser)
        ->post("/student/classes/{$class->id}/posts/{$post->id}/submit", [
            'content' => 'My valid submission',
            'files' => [$zipFile],
        ])
        ->assertRedirect();

    $submission = $post->submissions()->firstOrFail();
    expect($submission->attachments)->toHaveCount(1);
    $submissionAttachment = $submission->attachments[0];

    Storage::disk('private')->assertExists($submissionAttachment['path']);
    Storage::disk('public')->assertMissing($submissionAttachment['path']);

    $submissionFilename = basename($submissionAttachment['path']);

    // Submitting student can download
    actingAs($studentUser)
        ->get(route('classes.posts.submissions.attachments.download', [
            'class' => $class->id,
            'post' => $post->id,
            'file' => $submissionFilename,
        ]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=solution.zip')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // Other student forbidden
    $otherStudentUser = User::factory()->create(['role' => UserRole::Student]);
    actingAs($otherStudentUser)
        ->get(route('classes.posts.submissions.attachments.download', [
            'class' => $class->id,
            'post' => $post->id,
            'file' => $submissionFilename,
        ]))
        ->assertForbidden();

    // Faculty can download
    actingAs($facultyUser)
        ->get(route('classes.posts.submissions.attachments.download', [
            'class' => $class->id,
            'post' => $post->id,
            'file' => $submissionFilename,
        ]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=solution.zip')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('rejects html in class post API uploads, stores on private disk, and serves securely', function (): void {
    $school = School::factory()->create();
    $department = Department::factory()->create(['school_id' => $school->id]);
    $facultyUser = User::factory()->create(['role' => UserRole::Instructor]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'email' => $facultyUser->email,
        'faculty_id_number' => 'FAC-API-1',
    ]);
    $class = Classes::factory()->create([
        'school_id' => $school->id,
        'faculty_id' => $faculty->id,
    ]);

    $post = ClassPost::create([
        'class_id' => $class->id,
        'title' => 'API Class Post',
        'type' => ClassPostType::Announcement,
    ]);

    $htmlFile = UploadedFile::fake()->create('exploit.html', 10, 'text/html');

    // Reject HTML
    actingAs($facultyUser)
        ->postJson(route('api.class-posts.upload-attachment', $post->id), [
            'attachments' => [$htmlFile],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attachments.0']);

    // Accept valid PDF
    $pdfFile = UploadedFile::fake()->create('syllabus.pdf', 30, 'application/pdf');

    actingAs($facultyUser)
        ->postJson(route('api.class-posts.upload-attachment', $post->id), [
            'attachments' => [$pdfFile],
        ])
        ->assertOk();

    $post->refresh();
    expect($post->attachments)->toHaveCount(1);
    $path = $post->attachments[0];

    Storage::disk('private')->assertExists($path);
    Storage::disk('public')->assertMissing($path);

    // Download attachment via API
    actingAs($facultyUser)
        ->get(route('api.class-posts.download-attachment', [
            'id' => $post->id,
            'attachmentIndex' => 0,
        ]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename='.basename($path))
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
