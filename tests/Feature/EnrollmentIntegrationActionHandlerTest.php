<?php

declare(strict_types=1);

use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\Actions\EnrollmentIntegrationActionHandler;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Jobs\GenerateAssessmentPdfJob;
use App\Jobs\SendAssessmentNotificationJob;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Notifications\EnrollmentStatusUpdatedNotification;
use App\Notifications\StudentEnrolledVerified;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification as NotificationFacade;

it('registers executable integration actions with operator and payload schemas', function (): void {
    $registry = app(EnrollmentPolicyRegistry::class);
    $manifest = $registry->manifest();

    foreach ([
        'enrollment.verify_academic',
        'enrollment.verify_payment',
        'enrollment.assign_subjects',
        'enrollment.assign_classes',
        'enrollment.calculate_tuition',
        'enrollment.generate_assessment',
        'enrollment.notify',
    ] as $key) {
        expect($registry->action($key))->toBeInstanceOf(EnrollmentIntegrationActionHandler::class)
            ->and($manifest['actions'][$key]['operator_schema'])->toBeArray()
            ->and($manifest['actions'][$key]['payload_schema'])->toBeArray();
    }
});

it('queues assessments and notifications as retryable after-commit jobs', function (): void {
    Bus::fake();
    $student = Student::factory()->create(['email' => 'policy-student@example.com']);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id]);
    $context = EnrollmentContext::fromEnrollment($enrollment);
    $registry = app(EnrollmentPolicyRegistry::class);

    $assessment = $registry->action('enrollment.generate_assessment')->execute($context, [], 'assessment-job-1');
    $notification = $registry->action('enrollment.notify')->execute($context, ['notification' => 'assessment'], 'notification-job-1');

    expect($assessment->successful)->toBeTrue()
        ->and($notification->successful)->toBeTrue();
    Bus::assertDispatched(GenerateAssessmentPdfJob::class);
    Bus::assertDispatched(SendAssessmentNotificationJob::class);
});

it('dispatches academic clearance notification when configured on a workflow step', function (): void {
    NotificationFacade::fake();
    $student = Student::factory()->create(['email' => 'academic-notify@example.com']);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id]);
    $context = EnrollmentContext::fromEnrollment($enrollment);
    $registry = app(EnrollmentPolicyRegistry::class);

    $notification = $registry->action('enrollment.notify')->execute(
        $context,
        ['notification' => 'academic_verified'],
        'academic-notify-job-1'
    );

    expect($notification->successful)->toBeTrue();
    NotificationFacade::assertSentTo(
        new Illuminate\Notifications\AnonymousNotifiable,
        StudentEnrolledVerified::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'academic-notify@example.com'
    );
});

it('dispatches status update notification with updated enrollment status when status changes', function (): void {
    NotificationFacade::fake();
    $student = Student::factory()->create(['email' => 'status-notify@example.com', 'first_name' => 'Maria']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Documents Under Review',
        'current_step_key' => 'document_verification',
    ]);
    $context = EnrollmentContext::fromEnrollment($enrollment);
    $registry = app(EnrollmentPolicyRegistry::class);

    $notification = $registry->action('enrollment.notify')->execute(
        $context,
        ['notification' => 'status_update'],
        'status-notify-job-1'
    );

    expect($notification->successful)->toBeTrue();
    NotificationFacade::assertSentTo(
        new Illuminate\Notifications\AnonymousNotifiable,
        EnrollmentStatusUpdatedNotification::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'status-notify@example.com'
                && $notification->record->status === 'Documents Under Review'
                && $notification->record->current_step_key === 'document_verification';
        }
    );
});

it('dispatches customized email notification with bespoke subject, headline, body, and action button', function (): void {
    NotificationFacade::fake();
    $student = Student::factory()->create([
        'email' => 'custom-email@example.com',
        'first_name' => 'Carlos',
    ]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Entrance Exam Scheduled',
        'current_step_key' => 'entrance_exam',
    ]);
    $context = EnrollmentContext::fromEnrollment($enrollment);
    $registry = app(EnrollmentPolicyRegistry::class);

    $customConfig = [
        'notification' => 'custom',
        'subject' => 'Exam Instructions for {{ student_name }}: {{ status }}',
        'headline' => 'Your Entrance Exam is Scheduled',
        'custom_body' => "Dear {{ student_name }},\n\nPlease report to Room 302 on Monday at 9:00 AM.",
        'button_label' => 'View Examination Guidelines',
        'button_url' => 'https://example.com/exam-guidelines',
        'include_details' => true,
    ];

    $notification = $registry->action('enrollment.notify')->execute(
        $context,
        $customConfig,
        'custom-notify-job-1'
    );

    expect($notification->successful)->toBeTrue();
    NotificationFacade::assertSentTo(
        new Illuminate\Notifications\AnonymousNotifiable,
        EnrollmentStatusUpdatedNotification::class,
        function ($notification, $channels, $notifiable) {
            $mail = $notification->toMail($notifiable);
            $rendered = (string) $mail->render();

            return $notifiable->routes['mail'] === 'custom-email@example.com'
                && str_contains($mail->subject, 'Carlos')
                && str_contains($mail->subject, 'Entrance Exam Scheduled')
                && str_contains($rendered, 'Your Entrance Exam is Scheduled')
                && str_contains($rendered, 'Please report to Room 302 on Monday at 9:00 AM.')
                && str_contains($rendered, 'View Examination Guidelines')
                && str_contains($rendered, 'https://example.com/exam-guidelines');
        }
    );
});

it('assigns curriculum subjects and calculates tuition idempotently', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-08-01',
        'school_ending_date' => '2027-05-31',
        'semester' => 1,
    ]);
    $course = Course::factory()->create(['lec_per_unit' => 100, 'lab_per_unit' => 200]);
    $student = Student::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
    ]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 3,
        'laboratory' => 1,
    ]);
    $context = EnrollmentContext::fromEnrollment($enrollment);
    $registry = app(EnrollmentPolicyRegistry::class);

    $assignment = $registry->action('enrollment.assign_subjects')->execute(
        $context,
        ['source' => 'curriculum'],
        'integration-subjects-1',
    );
    $secondAssignment = $registry->action('enrollment.assign_subjects')->execute(
        $context,
        ['source' => 'curriculum'],
        'integration-subjects-1',
    );

    expect($assignment->successful)->toBeTrue()
        ->and($secondAssignment->successful)->toBeTrue()
        ->and($enrollment->subjectsEnrolled()->where('subject_id', $subject->id)->count())->toBe(1);

    $tuition = $registry->action('enrollment.calculate_tuition')->execute(
        $context,
        ['discount_percentage' => 10],
        'integration-tuition-1',
    );
    $secondTuition = $registry->action('enrollment.calculate_tuition')->execute(
        $context,
        ['discount_percentage' => 10],
        'integration-tuition-1',
    );

    expect($tuition->successful)->toBeTrue()
        ->and($secondTuition->successful)->toBeTrue()
        ->and($secondTuition->metadata['already_exists'])->toBeTrue()
        ->and($enrollment->studentTuition()->count())->toBe(1);
});

it('skips empty public manual subject selections but rejects empty staff submissions', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
    ]);
    $handler = app(EnrollmentPolicyRegistry::class)->action('enrollment.assign_subjects');
    $configuration = [
        'source' => 'runtime_payload',
        'runtime_payload' => ['subjects' => []],
    ];

    $public = $handler->execute(
        EnrollmentContext::fromEnrollment($enrollment, 'public'),
        $configuration,
        'public-empty-subjects',
    );
    $administrator = $handler->execute(
        EnrollmentContext::fromEnrollment($enrollment, 'administrator'),
        $configuration,
        'administrator-empty-subjects',
    );

    expect($public->successful)->toBeTrue()
        ->and($public->metadata['skipped'])->toBeTrue()
        ->and($administrator->successful)->toBeFalse()
        ->and($administrator->message)->toBe('The enrollment submission does not contain any subjects.')
        ->and($enrollment->subjectsEnrolled()->count())->toBe(0);
});

it('reserves an available class without duplicating enrollment on retry', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
    ]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
    ]);
    $subjectEnrollment = SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'school_id' => $enrollment->school_id,
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'class_id' => null,
        'is_modular' => false,
        'exclude_from_tuition' => false,
        'lecture_fee' => 0,
        'laboratory_fee' => 0,
        'enrolled_lecture_units' => $subject->lecture,
        'enrolled_laboratory_units' => $subject->laboratory,
    ]);
    $class = Classes::factory()->create([
        'school_id' => $enrollment->school_id,
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'maximum_slots' => 1,
    ]);
    $handler = app(EnrollmentPolicyRegistry::class)->action('enrollment.assign_classes');
    $context = EnrollmentContext::fromEnrollment($enrollment);

    $first = $handler->execute($context, ['mode' => 'first_available'], 'integration-class-1');
    $second = $handler->execute($context, ['mode' => 'first_available'], 'integration-class-1');

    expect($first->successful)->toBeTrue()
        ->and($second->successful)->toBeTrue()
        ->and($subjectEnrollment->refresh()->class_id)->toBe($class->id)
        ->and(ClassEnrollment::query()->where('student_id', $student->id)->where('class_id', $class->id)->count())->toBe(1);
});

it('materializes NSTP, modular, miscellaneous, and lecture-only discount defaults', function (): void {
    $course = Course::factory()->create([
        'lec_per_unit' => 100,
        'lab_per_unit' => 200,
        'miscellaneous' => 3500,
        'miscelaneous' => 3500,
    ]);
    $student = Student::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
    ]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'NSTP-1',
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 3,
        'laboratory' => 1,
    ]);
    $registry = app(EnrollmentPolicyRegistry::class);
    $context = EnrollmentContext::fromEnrollment($enrollment);

    $assignment = $registry->action('enrollment.assign_subjects')->execute($context, [
        'source' => 'runtime_payload',
        'runtime_payload' => ['subjects' => [[
            'subject_id' => $subject->id,
            'is_modular' => true,
        ]]],
    ], 'pricing-subject-1');
    $tuition = $registry->action('enrollment.calculate_tuition')->execute($context, [
        'nstp_lecture_multiplier' => 0.5,
        'modular_laboratory_multiplier' => 0.5,
        'modular_fee' => 2400,
        'discount_scope' => 'lecture_only',
        'runtime_payload' => ['discount_percentage' => 10, 'miscellaneous_fee' => 3600],
    ], 'pricing-tuition-1');
    $record = $enrollment->studentTuition()->sole();

    expect($assignment->successful)->toBeTrue()
        ->and($tuition->successful)->toBeTrue()
        ->and((float) $record->total_lectures)->toBe(180.0)
        ->and((float) $record->total_laboratory)->toBe(100.0)
        ->and((float) $record->total_tuition)->toBe(2680.0)
        ->and((float) $record->total_miscelaneous_fees)->toBe(3600.0)
        ->and((float) $record->overall_tuition)->toBe(6280.0);
});
