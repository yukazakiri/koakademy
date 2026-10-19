<?php

declare(strict_types=1);

use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Enrollment\EnrollmentPolicyManager;
use App\Enrollment\EnrollmentPolicyPreset;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Enrollment\Exceptions\EnrollmentTransitionException;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Features\DynamicEnrollmentPolicies;
use App\Jobs\GenerateAssessmentPdfJob;
use App\Jobs\SendAssessmentNotificationJob;
use App\Models\Account;
use App\Models\AdditionalFee;
use App\Models\AdminTransaction;
use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentRequirement;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentStatusRecord;
use App\Models\StudentTransaction;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\EnrollmentStatusUpdatedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->actor->givePermissionTo(Permission::findOrCreate('Update:StudentEnrollment', 'web'));
    $this->actingAs($this->actor);
    Feature::for($this->actor)->activate(DynamicEnrollmentPolicies::class);

    $this->publishEnrollmentPolicy = function (array $configuration, School $school): EnrollmentPolicy {
        $manager = app(EnrollmentPolicyManager::class);
        $policy = $manager->create([
            'name' => 'Runtime policy '.fake()->uuid(),
            'school_id' => $school->id,
            'configuration' => $configuration,
        ], $this->actor);
        $manager->publish($policy, $policy->versions->first(), $this->actor);

        return $policy->refresh();
    };

    $this->submissionFor = function (
        Student $student,
        Subject $subject,
        string $idempotencyKey,
        array $additionalFees = [],
        array $billingOverrides = [],
    ): EnrollmentSubmissionData {
        return new EnrollmentSubmissionData(
            enrollmentAttributes: [
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'course_id' => $student->course_id,
                'semester' => 1,
                'academic_year' => 1,
                'school_year' => '2035 - 2036',
            ],
            subjects: [[
                'subject_id' => $subject->id,
                'is_modular' => false,
                'lecture_fee' => 900,
                'laboratory_fee' => 0,
            ]],
            additionalFees: $additionalFees,
            billingOverrides: $billingOverrides,
            channel: 'administrator',
            idempotencyKey: $idempotencyKey,
            actor: $this->actor,
        );
    };
});

it('routes API enrollment creation through the typed policy submission flow', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
    ]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
    ]);
    $class = App\Models\Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2035 - 2036',
    ]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = collect($configuration['workflow']['steps'][0]['actions'])
        ->reject(fn (array $action): bool => $action['handler'] === 'enrollment.assign_classes')
        ->values()
        ->all();
    ($this->publishEnrollmentPolicy)($configuration, $school);
    Sanctum::actingAs($this->actor);

    $payload = [
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'subjects' => [[
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'is_modular' => false,
        ]],
    ];
    $headers = ['Idempotency-Key' => 'api-submission-1'];

    $first = $this->postJson(route('api.enrollments.store'), $payload, $headers);
    $second = $this->postJson(route('api.enrollments.store'), $payload, $headers);

    $first->assertCreated();
    $second->assertCreated();
    $enrollment = StudentEnrollment::query()->sole();

    expect($enrollment->workflow_runtime)->toBe(StudentEnrollment::WorkflowRuntimePolicyV1)
        ->and($enrollment->submission_channel)->toBe('api')
        ->and($enrollment->school_id)->toBe($school->id)
        ->and($enrollment->enrollment_policy_snapshot_id)->not->toBeNull();
});

it('executes configured entry actions in order and makes submission retries idempotent', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [
        ['key' => 'fees', 'handler' => 'enrollment.assign_additional_fees', 'configuration' => []],
        ['key' => 'subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
        ['key' => 'tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
    ];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $submission = ($this->submissionFor)(
        $student,
        $subject,
        'entry-order-1',
        [['fee_name' => 'Technology fee', 'amount' => 250]],
        ['miscellaneous_fee' => 4200, 'discount_percentage' => 10],
    );

    $first = app(EnrollmentWorkflowCoordinator::class)->submit($submission);
    $second = app(EnrollmentWorkflowCoordinator::class)->submit($submission);
    $event = EnrollmentWorkflowEvent::query()
        ->where('student_enrollment_id', $first->id)
        ->where('event_type', 'initialized')
        ->sole();

    expect($second->id)->toBe($first->id)
        ->and($first->subjectsEnrolled()->count())->toBe(1)
        ->and($first->additionalFees()->count())->toBe(1)
        ->and($first->studentTuition()->count())->toBe(1)
        ->and((float) $first->studentTuition->total_miscelaneous_fees)->toBe(4200.0)
        ->and((int) $first->studentTuition->discount)->toBe(10)
        ->and(EnrollmentWorkflowEvent::query()->where('student_enrollment_id', $first->id)->where('event_type', 'initialized')->count())->toBe(1)
        ->and(collect($event->result['actions'])->pluck('key')->all())->toBe([
            'enrollment.assign_additional_fees',
            'enrollment.assign_subjects',
            'enrollment.calculate_tuition',
        ]);
});

it('skips ineligible verification branches and replays retries after leaving the source step', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id]);
    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [];
    $configuration['workflow']['steps'][0]['transitions'] = [
        [
            'key' => 'public_review', 'label' => 'Public review', 'to' => 'academic_verified', 'fallback' => false,
            'conditions' => [['handler' => 'availability.channel', 'configuration' => ['allowed' => ['public']]]],
        ],
        ['key' => 'assisted_review', 'label' => 'Assisted review', 'to' => 'academic_verified', 'fallback' => true, 'conditions' => []],
    ];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'branched-academic'));

    $available = app(App\Enrollment\EnrollmentTransitionEngine::class)->availableTransitions($enrollment, $this->actor);
    expect(array_column($available, 'key'))->toBe(['assisted_review']);
    expect(fn () => $coordinator->transition($enrollment, $this->actor, 'public_review', [], 'ineligible-explicit-route'))
        ->toThrow(EnrollmentTransitionException::class, 'No transition is available');
    expect($enrollment->refresh()->current_step_key)->toBe('submitted');

    $result = $coordinator->verifyAcademic($enrollment, $this->actor, 'branched-review');
    $retry = $coordinator->verifyAcademic($enrollment->refresh(), $this->actor, 'branched-review');

    expect($result->successful)->toBeTrue()
        ->and($retry->successful)->toBeTrue()
        ->and($retry->message)->toContain('already processed')
        ->and($enrollment->refresh()->current_step_key)->toBe('academic_verified')
        ->and(EnrollmentWorkflowEvent::query()->where('event_type', 'transition_succeeded')->sole()->result['transition_key'])
        ->toBe('assisted_review');
});

it('does not advertise routes to unauthorized staff or from terminal stages', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id]);
    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $enrollment = app(EnrollmentWorkflowCoordinator::class)->submit(($this->submissionFor)($student, $subject, 'route-authorization'));
    $unauthorized = User::factory()->create();
    $engine = app(App\Enrollment\EnrollmentTransitionEngine::class);

    expect($engine->availableTransitions($enrollment, $unauthorized))->toBe([]);
    expect(fn () => $engine->transition($enrollment, $unauthorized, null, [], 'unauthorized-route'))
        ->toThrow(AuthorizationException::class);
    expect($enrollment->refresh()->current_step_key)->toBe('submitted');
    $enrollment->forceFill(['current_step_key' => 'completed', 'terminal_outcome' => 'completed'])->save();
    expect($engine->availableTransitions($enrollment, $this->actor))->toBe([]);
});

it('honors the selected cashier route and refuses unknown routes without recording payment', function (): void {
    $this->actor->update(['role' => UserRole::Admin]);
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id]);
    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [];
    $configuration['workflow']['steps'][1]['transitions'] = [
        [
            'key' => 'alternate_payment', 'label' => 'Alternate payment', 'to' => 'alternate_completed', 'fallback' => false,
            'conditions' => [['handler' => 'availability.channel', 'configuration' => ['allowed' => ['administrator']]]],
        ],
        ['key' => 'payment_review', 'label' => 'Standard payment', 'to' => 'completed', 'fallback' => true, 'conditions' => []],
    ];
    $configuration['workflow']['steps'][2]['actions'] = [[
        'key' => 'payment_status', 'handler' => 'enrollment.verify_payment',
        'configuration' => ['receipt_mode' => 'required', 'record_transaction' => true],
    ]];
    $alternate = $configuration['workflow']['steps'][2];
    $alternate['key'] = 'alternate_completed';
    $configuration['workflow']['steps'][] = $alternate;
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'selected-cashier'));
    $coordinator->verifyAcademic($enrollment, $this->actor, 'selected-cashier-academic');

    $this->post(route('administrators.enrollments.verify-cashier', $enrollment), [
        'transition_key' => 'missing-route',
        'invoicenumber' => 'INV-INVALID-ROUTE',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ])->assertRedirect()->assertSessionHas('flash.error', 'No transition is available for the current enrollment context.');
    expect($enrollment->refresh()->current_step_key)->toBe('academic_verified')
        ->and(Transaction::query()->count())->toBe(0);

    $this->post(route('administrators.enrollments.verify-cashier', $enrollment), [
        'transition_key' => 'payment_review',
        'invoicenumber' => 'INV-SELECTED-ROUTE',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ])->assertRedirect()->assertSessionHas('flash.success', 'Successfully enrolled student.');

    expect($enrollment->refresh()->current_step_key)->toBe('completed')
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->count())->toBe(1);
});

it('does not run enrollment behavior that the blueprint omits', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [
        ['key' => 'subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
    ];
    ($this->publishEnrollmentPolicy)($configuration, $school);

    $enrollment = app(EnrollmentWorkflowCoordinator::class)->submit(($this->submissionFor)(
        $student,
        $subject,
        'omitted-actions-1',
        [['fee_name' => 'Ignored fee', 'amount' => 250]],
        ['miscellaneous_fee' => 9999],
    ));

    expect($enrollment->subjectsEnrolled()->count())->toBe(1)
        ->and($enrollment->studentTuition()->exists())->toBeFalse()
        ->and(AdditionalFee::query()->where('enrollment_id', $enrollment->id)->exists())->toBeFalse();
});

it('blocks a configured transition until its requirement is verified or audited as waived', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['requirements'] = [[
        'key' => 'form_138',
        'label' => 'Form 138',
        'required' => true,
        'enforcement_step' => 'academic_verified',
    ]];
    $configuration['workflow']['steps'][0]['actions'] = [];
    $configuration['workflow']['steps'][2]['actions'] = [];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'requirement-gate-1'));
    $requirement = $enrollment->requirements()->sole();

    expect(fn () => $coordinator->verifyAcademic($enrollment, $this->actor, 'blocked-requirement-1'))
        ->toThrow(EnrollmentTransitionException::class, 'verified or waived');
    expect($enrollment->refresh()->current_step_key)->toBe('submitted')
        ->and($requirement->status)->toBe(EnrollmentRequirement::Pending);

    $coordinator->waiveRequirement($requirement, $this->actor, 'Original was destroyed in a flood.', 'waive-requirement-1');
    $result = $coordinator->verifyAcademic($enrollment->refresh(), $this->actor, 'allowed-requirement-1');

    expect($result->successful)->toBeTrue()
        ->and($requirement->refresh()->status)->toBe(EnrollmentRequirement::Waived)
        ->and($requirement->waiver_reason)->toBe('Original was destroyed in a flood.')
        ->and(EnrollmentWorkflowEvent::query()->where('event_type', 'requirement_waived')->value('actor_id'))->toBe($this->actor->id);
});

it('supports no-payment and authorized no-receipt workflows without inventing payment side effects', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::configuration('no_payment');
    $configuration['workflow']['steps'][0]['actions'] = [];
    $configuration['workflow']['steps'][2]['actions'] = [[
        'key' => 'no_receipt',
        'handler' => 'enrollment.verify_payment',
        'configuration' => [
            'receipt_mode' => 'none',
            'record_transaction' => false,
            'allow_no_receipt' => true,
        ],
    ]];
    $configuration['workflow']['steps'][1]['transitions'][0]['requires_reason'] = true;
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'no-receipt-1'));
    $coordinator->verifyAcademic($enrollment, $this->actor, 'no-receipt-academic-1');

    $result = $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'without_receipt' => true,
        'reason' => 'Authorized scholarship enrollment.',
    ], 'no-receipt-payment-1');

    expect($result->terminalOutcome)->toBe('completed')
        ->and(Transaction::query()->count())->toBe(0)
        ->and(StudentTransaction::query()->count())->toBe(0)
        ->and(EnrollmentWorkflowEvent::query()->where('event_type', 'transition_succeeded')->latest('id')->value('reason'))
        ->toBe('Authorized scholarship enrollment.');
});

it('completes a no-payment blueprint without creating a payment record', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::configuration('no_payment');
    $configuration['workflow']['steps'][0]['actions'] = [];
    $configuration['workflow']['steps'][2]['actions'] = [];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'no-payment-1'));

    $coordinator->verifyAcademic($enrollment, $this->actor, 'no-payment-academic-1');
    $result = $coordinator->transition($enrollment->refresh(), $this->actor, null, [], 'no-payment-complete-1');

    expect($result->terminalOutcome)->toBe('completed')
        ->and(Transaction::query()->count())->toBe(0)
        ->and(StudentTransaction::query()->count())->toBe(0);
});

it('executes the explicit default journey through synchronization and completion', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'status' => StudentStatus::Applicant,
        'email' => 'policy-student@example.test',
    ]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    ($this->publishEnrollmentPolicy)(EnrollmentPolicyPreset::standard(), $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'default-journey-1'));

    $coordinator->verifyAcademic($enrollment, $this->actor, 'default-journey-academic-1');
    $result = $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => 'INV-DEFAULT-001',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ], 'default-journey-payment-1');

    $completed = $enrollment->refresh();
    $event = EnrollmentWorkflowEvent::query()
        ->where('student_enrollment_id', $enrollment->id)
        ->where('event_type', 'transition_succeeded')
        ->latest('id')
        ->firstOrFail();

    expect($result->terminalOutcome)->toBe('completed')
        ->and($completed->terminal_outcome)->toBe('completed')
        ->and($student->refresh()->status)->toBe(StudentStatus::Enrolled)
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->count())->toBe(1)
        ->and(collect($event->result['actions'])->pluck('key')->all())->toBe([
            'enrollment.verify_payment',
            'enrollment.assign_classes',
            'enrollment.sync_student',
            'enrollment.set_outcome',
            'enrollment.generate_assessment',
            'enrollment.notify',
        ]);
    Queue::assertPushed(GenerateAssessmentPdfJob::class, 1);
    Queue::assertPushed(SendAssessmentNotificationJob::class, 1);
});

it('updates enrollment status in database before executing notification action so student receives email with new status', function (): void {
    NotificationFacade::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'email' => 'status-change-order@example.test',
        'first_name' => 'Juan',
    ]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);

    $configuration = [
        'schema_version' => 1,
        'rules' => [],
        'requirements' => [],
        'assignment' => ['strategy' => 'assignment.manual', 'configuration' => []],
        'billing' => ['strategy' => 'billing.course_rate', 'configuration' => []],
        'workflow' => [
            'steps' => [
                [
                    'key' => 'intake',
                    'label' => 'Intake',
                    'entry' => true,
                    'terminal' => false,
                    'status' => 'Pending Review',
                    'actions' => [],
                    'transitions' => [
                        [
                            'key' => 'to_interview',
                            'label' => 'Schedule interview',
                            'to' => 'interview',
                            'fallback' => true,
                            'conditions' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'interview',
                    'label' => 'Interview Stage',
                    'entry' => false,
                    'terminal' => false,
                    'status' => 'Interview Scheduled',
                    'actions' => [
                        [
                            'key' => 'notify_status',
                            'handler' => 'enrollment.notify',
                            'configuration' => ['notification' => 'status_update'],
                        ],
                    ],
                    'transitions' => [
                        [
                            'key' => 'to_enrolled',
                            'label' => 'Complete enrollment',
                            'to' => 'enrolled',
                            'fallback' => true,
                            'conditions' => [],
                        ],
                    ],
                ],
                [
                    'key' => 'enrolled',
                    'label' => 'Enrolled',
                    'entry' => false,
                    'terminal' => true,
                    'outcome' => 'completed',
                    'status' => 'Officially Enrolled',
                    'actions' => [],
                    'transitions' => [],
                ],
            ],
        ],
    ];

    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'status-order-submit-1'));

    expect($enrollment->status)->toBe('Pending Review');

    // Transition from Intake to Interview
    $result = $coordinator->transition($enrollment, $this->actor, 'to_interview', [], 'status-order-transition-1');

    expect($result->successful)->toBeTrue()
        ->and($result->toStepKey)->toBe('interview')
        ->and($enrollment->refresh()->status)->toBe('Interview Scheduled');

    NotificationFacade::assertSentTo(
        new Illuminate\Notifications\AnonymousNotifiable,
        EnrollmentStatusUpdatedNotification::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'status-change-order@example.test'
                && $notification->record->status === 'Interview Scheduled'
                && $notification->record->current_step_key === 'interview';
        }
    );
});

it('defers minimum payment rules until the terminal transition', function (string $type, float $value): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create([
        'school_id' => $school->id,
        'miscellaneous' => 0,
        'miscelaneous' => 0,
    ]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'status' => StudentStatus::Applicant,
    ]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
    ]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['rules'][] = [
        'key' => 'minimum_payment',
        'handler' => 'billing.minimum_payment',
        'configuration' => ['type' => $type, 'value' => $value],
    ];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);

    $enrollment = $coordinator->submit(($this->submissionFor)(
        $student,
        $subject,
        "minimum-payment-{$type}-submission",
        billingOverrides: ['miscellaneous_fee' => 0],
    ));
    expect($enrollment->current_step_key)->toBe('submitted');

    $coordinator->verifyAcademic($enrollment, $this->actor, "minimum-payment-{$type}-academic");
    expect(fn () => $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => "INV-MINIMUM-{$type}-LOW",
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 400],
    ], "minimum-payment-{$type}-low"))
        ->toThrow(EnrollmentTransitionException::class, 'configured minimum payment');

    expect($enrollment->refresh()->current_step_key)->toBe('academic_verified')
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->exists())->toBeFalse()
        ->and($student->refresh()->status)->toBe(StudentStatus::Applicant);
    Queue::assertNotPushed(GenerateAssessmentPdfJob::class);
    Queue::assertNotPushed(SendAssessmentNotificationJob::class);

    $result = $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => "INV-MINIMUM-{$type}-OK",
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ], "minimum-payment-{$type}-ok");

    expect($result->terminalOutcome)->toBe('completed')
        ->and($enrollment->refresh()->current_step_key)->toBe('completed')
        ->and($student->refresh()->status)->toBe(StudentStatus::Enrolled);
})->with([
    'fixed amount' => ['fixed', 500],
    'percentage of tuition' => ['percentage', 50],
]);

it('restores student status records and account linkage when reopening a terminal enrollment', function (bool $existingStatusRecord): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'email' => 'reopen-policy-student@example.com',
        'status' => StudentStatus::Applicant,
    ]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
    ]);
    if ($existingStatusRecord) {
        StudentStatusRecord::query()->create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'academic_year' => '2035 - 2036',
            'semester' => 1,
            'status' => StudentStatus::Applicant,
        ]);
    }
    $account = Account::factory()->create([
        'email' => $student->email,
        'role' => 'guest',
        'person_id' => null,
        'person_type' => null,
        'is_active' => true,
    ]);
    ($this->publishEnrollmentPolicy)(EnrollmentPolicyPreset::standard(), $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'reopen-state-submission'));
    $coordinator->verifyAcademic($enrollment, $this->actor, 'reopen-state-academic');
    $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => 'INV-REOPEN-STATE',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ], 'reopen-state-payment');

    $synchronizedStatus = StudentStatusRecord::query()
        ->where('student_id', $student->id)
        ->where('academic_year', '2035 - 2036')
        ->where('semester', 1)
        ->sole();
    expect($student->refresh()->status)->toBe(StudentStatus::Enrolled)
        ->and($synchronizedStatus->status)->toBe(StudentStatus::Enrolled)
        ->and($account->refresh()->role)->toBe('student')
        ->and($account->person_id)->toBe($student->id)
        ->and($account->person_type)->toBe(Student::class);

    $this->actor->givePermissionTo(Permission::findOrCreate('Reopen:StudentEnrollment', 'web'));
    $result = $coordinator->reopen(
        $enrollment->refresh(),
        $this->actor,
        'academic_verified',
        'Correct the cashier verification.',
        'reopen-state-reversal',
    );

    expect($result->successful)->toBeTrue()
        ->and($enrollment->refresh()->current_step_key)->toBe('academic_verified')
        ->and($student->refresh()->status)->toBe(StudentStatus::Applicant)
        ->and($account->refresh()->role)->toBe('guest')
        ->and($account->person_id)->toBeNull()
        ->and($account->person_type)->toBeNull()
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->exists())->toBeFalse()
        ->and(StudentStatusRecord::query()
            ->where('student_id', $student->id)
            ->where('academic_year', '2035 - 2036')
            ->where('semester', 1)
            ->exists())->toBe($existingStatusRecord)
        ->and(data_get(
            EnrollmentWorkflowEvent::query()->where('event_type', 'reopened')->latest('id')->value('result'),
            'student_state_reversal.restored',
        ))->toBeTrue();
    if ($existingStatusRecord) {
        expect($synchronizedStatus->refresh()->status)->toBe(StudentStatus::Applicant);
    }
})->with([
    'restores an existing period status' => true,
    'removes a status created by completion' => false,
]);

it('keeps normalized billing behavior pinned after a newer policy version is published', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id, 'miscellaneous' => 3500, 'miscelaneous' => 3500]);
    $firstStudent = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $secondStudent = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 3,
        'laboratory' => 1,
    ]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [
        ['key' => 'subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
        ['key' => 'tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
    ];
    $configuration['billing']['configuration']['modular_fee'] = 1000;
    $configuration['billing']['configuration']['modular_laboratory_multiplier'] = 0.5;
    $policy = ($this->publishEnrollmentPolicy)($configuration, $school);
    $firstSubmission = ($this->submissionFor)($firstStudent, $subject, 'pinned-billing-first');
    $firstSubmission = new EnrollmentSubmissionData(
        enrollmentAttributes: $firstSubmission->enrollmentAttributes,
        subjects: [[
            'subject_id' => $subject->id,
            'is_modular' => true,
            'lecture_fee' => 900,
            'laboratory_fee' => 200,
        ]],
        channel: $firstSubmission->channel,
        idempotencyKey: $firstSubmission->idempotencyKey,
        actor: $this->actor,
    );
    $first = app(EnrollmentWorkflowCoordinator::class)->submit($firstSubmission);

    $updated = $configuration;
    $updated['billing']['configuration']['modular_fee'] = 9000;
    $manager = app(EnrollmentPolicyManager::class);
    $draft = $manager->saveDraft($policy, $updated, 'Change modular pricing for future enrollments.', $this->actor);
    $manager->publish($policy, $draft, $this->actor);
    $secondSubmission = ($this->submissionFor)($secondStudent, $subject, 'pinned-billing-second');
    $secondSubmission = new EnrollmentSubmissionData(
        enrollmentAttributes: $secondSubmission->enrollmentAttributes,
        subjects: [[
            'subject_id' => $subject->id,
            'is_modular' => true,
            'lecture_fee' => 900,
            'laboratory_fee' => 200,
        ]],
        channel: $secondSubmission->channel,
        idempotencyKey: $secondSubmission->idempotencyKey,
        actor: $this->actor,
    );
    $second = app(EnrollmentWorkflowCoordinator::class)->submit($secondSubmission);

    expect((float) $first->studentTuition->total_laboratory)->toBe(200.0);
    expect(data_get($first->policySnapshot->configuration, 'billing.configuration.modular_fee'))->toBe(1000)
        ->and(data_get($first->refresh()->policySnapshot->configuration, 'billing.configuration.modular_fee'))->toBe(1000)
        ->and(data_get($second->policySnapshot->configuration, 'billing.configuration.modular_fee'))->toBe(9000)
        ->and((float) $second->studentTuition->overall_tuition - (float) $first->studentTuition->overall_tuition)->toBe(8000.0);
});

it('pins resolved course rates and miscellaneous fees before a delayed tuition action', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create([
        'school_id' => $school->id,
        'lec_per_unit' => 300,
        'lab_per_unit' => 200,
        'miscelaneous' => 3500,
    ]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 3,
        'laboratory' => 1,
    ]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [];
    $configuration['workflow']['steps'][1]['actions'] = [
        ['key' => 'delayed_subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
        ['key' => 'delayed_tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
    ];
    $configuration['workflow']['steps'][2]['actions'] = [];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'pinned-course-rates-1'));

    $course->update(['lec_per_unit' => 900, 'lab_per_unit' => 800, 'miscelaneous' => 9900]);
    $coordinator->transition($enrollment, $this->actor, 'academic_review', [
        'delayed_subjects' => ['subjects' => [['subject_id' => $subject->id, 'is_modular' => false]]],
    ], 'pinned-course-rates-transition-1');

    expect(data_get($enrollment->policySnapshot->configuration, 'billing.configuration.course_lecture_rate_per_unit'))->toBe(300)
        ->and(data_get($enrollment->policySnapshot->configuration, 'billing.configuration.course_laboratory_rate_per_unit'))->toBe(200)
        ->and(data_get($enrollment->policySnapshot->configuration, 'billing.configuration.course_miscellaneous_fee'))->toBe(3500)
        ->and((float) $enrollment->refresh()->studentTuition->overall_tuition)->toBe(4900.0);
});

it('enforces payment gates, attributes the actor, and reverses only enrollment-scoped payments', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [
        ['key' => 'subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
        ['key' => 'tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
    ];
    $configuration['workflow']['steps'][2]['actions'] = [[
        'key' => 'payment_status',
        'handler' => 'enrollment.verify_payment',
        'configuration' => ['receipt_mode' => 'required', 'record_transaction' => true, 'allow_no_receipt' => false],
    ]];
    $configuration['billing']['allowed_payment_methods'] = ['Bank Transfer'];
    $configuration['billing']['configuration']['minimum_payment'] = ['type' => 'fixed', 'value' => 500];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'payment-gates-1'));
    $coordinator->verifyAcademic($enrollment, $this->actor, 'payment-gates-academic-1');

    expect(fn () => $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => 'INV-INVALID-METHOD',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ], 'payment-invalid-method-1'))->toThrow(EnrollmentTransitionException::class, 'not allowed');
    expect(fn () => $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => 'INV-BELOW-MINIMUM',
        'payment_method' => 'Bank Transfer',
        'settlements' => ['tuition_fee' => 100],
    ], 'payment-below-minimum-1'))->toThrow(EnrollmentTransitionException::class, 'minimum');

    $payload = [
        'invoicenumber' => 'INV-VALID-001',
        'payment_method' => 'Bank Transfer',
        'settlements' => [
            'tuition_fee' => 500,
            'registration_fee' => 125,
            'others' => 75,
        ],
    ];
    $first = $coordinator->verifyPayment($enrollment->refresh(), $this->actor, $payload, 'payment-valid-1');
    $retry = $coordinator->verifyPayment($enrollment->refresh(), $this->actor, $payload, 'payment-valid-1');

    expect($first->successful)->toBeTrue()
        ->and($retry->message)->toContain('already processed')
        ->and(Transaction::query()->count())->toBe(1)
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->count())->toBe(1)
        ->and((float) StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->value('amount'))->toBe(700.0)
        ->and(AdminTransaction::query()->value('admin_id'))->toBe($this->actor->id)
        ->and((float) AdminTransaction::query()->value('amount'))->toBe(700.0)
        ->and(EnrollmentWorkflowEvent::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('event_type', 'transition_succeeded')
            ->latest('id')
            ->value('actor_id'))->toBe($this->actor->id);

    $otherEnrollment = StudentEnrollment::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'workflow_runtime' => StudentEnrollment::WorkflowRuntimeLegacy,
    ]);
    $unrelatedTransaction = Transaction::query()->create([
        'description' => 'Unrelated enrollment payment',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 200],
        'status' => 'Paid',
        'invoicenumber' => 'INV-UNRELATED-001',
        'transaction_date' => now(),
    ]);
    StudentTransaction::query()->create([
        'student_id' => $otherEnrollment->student_id,
        'student_enrollment_id' => $otherEnrollment->id,
        'transaction_id' => $unrelatedTransaction->id,
        'amount' => 200,
        'status' => 'Paid',
    ]);
    $this->actor->givePermissionTo(Permission::findOrCreate('Reopen:StudentEnrollment', 'web'));
    $coordinator->reopen(
        $enrollment->refresh(),
        $this->actor,
        'academic_verified',
        'Correct the enrollment-scoped payment.',
        'payment-reopen-1',
    );

    expect(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->exists())->toBeFalse()
        ->and(Transaction::query()->whereKey($unrelatedTransaction->id)->exists())->toBeTrue()
        ->and(EnrollmentWorkflowEvent::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('event_type', 'reopened')
            ->value('actor_id'))->toBe($this->actor->id);
});

it('records separate additional fees as idempotent enrollment-scoped ledger transactions', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [
        ['key' => 'subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
        ['key' => 'fees', 'handler' => 'enrollment.assign_additional_fees', 'configuration' => []],
        ['key' => 'tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
    ];
    $configuration['workflow']['steps'][2]['actions'] = [[
        'key' => 'payment_status',
        'handler' => 'enrollment.verify_payment',
        'configuration' => ['receipt_mode' => 'required', 'record_transaction' => true],
    ]];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)(
        $student,
        $subject,
        'separate-fee-submission-1',
        [[
            'fee_name' => 'Laboratory kit',
            'amount' => 750,
            'is_separate_transaction' => true,
        ]],
    ));
    $fee = $enrollment->additionalFees()->sole();
    $coordinator->verifyAcademic($enrollment, $this->actor, 'separate-fee-academic-1');

    expect(fn () => $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => 'INV-TUITION-001',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ], 'separate-fee-missing-invoice-1'))
        ->toThrow(EnrollmentTransitionException::class, 'Transaction number is required for Laboratory kit');
    expect(Transaction::query()->count())->toBe(0)
        ->and(StudentTransaction::query()->count())->toBe(0)
        ->and(AdminTransaction::query()->count())->toBe(0);

    $payload = [
        'invoicenumber' => 'INV-TUITION-001',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
        "separate_fee_{$fee->id}_transaction" => 'INV-LAB-KIT-001',
    ];
    $first = $coordinator->verifyPayment($enrollment->refresh(), $this->actor, $payload, 'separate-fee-payment-1');
    $retry = $coordinator->verifyPayment($enrollment->refresh(), $this->actor, $payload, 'separate-fee-payment-1');
    $separateTransaction = Transaction::query()->where('invoicenumber', 'INV-LAB-KIT-001')->sole();

    expect($first->successful)->toBeTrue()
        ->and($retry->message)->toContain('already processed')
        ->and(Transaction::query()->count())->toBe(2)
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->count())->toBe(2)
        ->and(AdminTransaction::query()->where('admin_id', $this->actor->id)->count())->toBe(2)
        ->and($separateTransaction->description)->toBe('Payment for Laboratory kit')
        ->and($separateTransaction->settlements)->toBe(['others' => 750])
        ->and((float) StudentTransaction::query()->where('transaction_id', $separateTransaction->id)->value('amount'))->toBe(750.0)
        ->and($fee->refresh()->transaction_number)->toBe('INV-LAB-KIT-001')
        ->and(data_get($first->actions, '0.metadata.separate_transaction_count'))->toBe(1);

    $this->actor->givePermissionTo(Permission::findOrCreate('Reopen:StudentEnrollment', 'web'));
    $coordinator->reopen(
        $enrollment->refresh(),
        $this->actor,
        'academic_verified',
        'Correct the separate fee receipts.',
        'separate-fee-reopen-1',
    );

    expect(Transaction::query()->count())->toBe(0)
        ->and(StudentTransaction::query()->count())->toBe(0)
        ->and(AdminTransaction::query()->count())->toBe(0)
        ->and($fee->refresh()->transaction_number)->toBeNull();
});

it('forwards only enrollment-owned separate fee invoices through the administrator cashier endpoint', function (): void {
    $this->actor->update(['role' => UserRole::Admin]);
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [
        ['key' => 'subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
        ['key' => 'fees', 'handler' => 'enrollment.assign_additional_fees', 'configuration' => []],
        ['key' => 'tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
    ];
    $configuration['workflow']['steps'][2]['actions'] = [[
        'key' => 'payment_status',
        'handler' => 'enrollment.verify_payment',
        'configuration' => ['receipt_mode' => 'required', 'record_transaction' => true],
    ]];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)(
        $student,
        $subject,
        'separate-fee-http-submission-1',
        [['fee_name' => 'ID card', 'amount' => 300, 'is_separate_transaction' => true]],
    ));
    $fee = $enrollment->additionalFees()->sole();
    $coordinator->verifyAcademic($enrollment, $this->actor, 'separate-fee-http-academic-1');

    $response = $this->post(route('administrators.enrollments.verify-cashier', $enrollment), [
        'invoicenumber' => 'INV-HTTP-TUITION-001',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
        "separate_fee_{$fee->id}_transaction" => 'INV-HTTP-ID-001',
        'separate_fee_999999_transaction' => 'MUST-NOT-BE-FORWARDED',
    ]);

    $response->assertRedirect()
        ->assertSessionHas('flash.success', 'Successfully enrolled student.');
    expect(Transaction::query()->count())->toBe(2)
        ->and(Transaction::query()->where('invoicenumber', 'INV-HTTP-ID-001')->exists())->toBeTrue()
        ->and(Transaction::query()->where('invoicenumber', 'MUST-NOT-BE-FORWARDED')->exists())->toBeFalse()
        ->and($fee->refresh()->transaction_number)->toBe('INV-HTTP-ID-001');
});

it('rolls back payments and after-commit jobs when a later configured action fails', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'email' => null,
    ]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['actions'] = [
        ['key' => 'subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
        ['key' => 'fees', 'handler' => 'enrollment.assign_additional_fees', 'configuration' => []],
        ['key' => 'tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
    ];
    $configuration['workflow']['steps'][2]['actions'] = [
        ['key' => 'payment_status', 'handler' => 'enrollment.verify_payment', 'configuration' => ['receipt_mode' => 'required', 'record_transaction' => true]],
        ['key' => 'assessment', 'handler' => 'enrollment.generate_assessment', 'configuration' => ['create_new_file' => false]],
        ['key' => 'notification', 'handler' => 'enrollment.notify', 'configuration' => ['notification' => 'assessment']],
    ];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)(
        $student,
        $subject,
        'rollback-actions-1',
        [['fee_name' => 'Rollback fee', 'amount' => 250, 'is_separate_transaction' => true]],
    ));
    $fee = $enrollment->additionalFees()->sole();
    $coordinator->verifyAcademic($enrollment, $this->actor, 'rollback-academic-1');

    expect(fn () => $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => 'INV-ROLLBACK-001',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
        "separate_fee_{$fee->id}_transaction" => 'INV-ROLLBACK-FEE-001',
    ], 'rollback-payment-1'))->toThrow(EnrollmentTransitionException::class, 'email address');

    expect($enrollment->refresh()->current_step_key)->toBe('academic_verified')
        ->and(Transaction::query()->count())->toBe(0)
        ->and(StudentTransaction::query()->count())->toBe(0)
        ->and(AdminTransaction::query()->count())->toBe(0)
        ->and($fee->refresh()->transaction_number)->toBeNull();
    Queue::assertNotPushed(GenerateAssessmentPdfJob::class);
    Queue::assertNotPushed(SendAssessmentNotificationJob::class);
});

it('cancels a policy enrollment, releases its reserved class seats and blocks further transitions', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'status' => StudentStatus::Applicant,
    ]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $class = App\Models\Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2035 - 2036',
    ]);
    ($this->publishEnrollmentPolicy)(EnrollmentPolicyPreset::standard(), $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'cancel-seat-submission'));
    $subjectEnrollment = $enrollment->subjectsEnrolled()->sole();
    $subjectEnrollment->update(['class_id' => $class->id]);
    $seat = App\Models\ClassEnrollment::factory()->createOne([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);
    $this->actor->givePermissionTo(Permission::findOrCreate('Cancel:StudentEnrollment', 'web'));

    $result = $coordinator->cancel(
        $enrollment->refresh(),
        $this->actor,
        'Applicant withdrew before verification.',
        'cancel-seat-1',
    );

    expect($result->successful)->toBeTrue()
        ->and($result->terminalOutcome)->toBe('cancelled')
        ->and($enrollment->refresh()->terminal_outcome)->toBe('cancelled')
        ->and($enrollment->current_step_key)->toBe('submitted')
        ->and($seat->refresh()->status)->toBeFalse()
        ->and($subjectEnrollment->refresh()->class_id)->toBeNull()
        ->and($student->refresh()->status)->toBe(StudentStatus::Applicant)
        ->and(data_get(EnrollmentWorkflowEvent::query()->where('event_type', 'cancelled')->latest('id')->value('result'), 'class_seats_released.0.class_id'))
        ->toBe($class->id)
        ->and(EnrollmentWorkflowEvent::query()->where('event_type', 'cancelled')->latest('id')->value('reason'))
        ->toBe('Applicant withdrew before verification.');

    expect(fn () => $coordinator->verifyAcademic($enrollment->refresh(), $this->actor, 'cancel-seat-academic'))
        ->toThrow(EnrollmentTransitionException::class, 'must be reopened');
    expect($enrollment->refresh()->current_step_key)->toBe('submitted')
        ->and(app(App\Enrollment\EnrollmentTransitionEngine::class)->availableTransitions($enrollment->refresh(), $this->actor))->toBe([]);

    $retry = $coordinator->cancel($enrollment->refresh(), $this->actor, 'Retried cancel.', 'cancel-seat-1');
    expect($retry->message)->toContain('already processed')
        ->and(EnrollmentWorkflowEvent::query()->where('event_type', 'cancelled')->count())->toBe(1);

    $again = $coordinator->cancel($enrollment->refresh(), $this->actor, 'Already cancelled.', 'cancel-seat-2');
    expect($again->successful)->toBeTrue()
        ->and($again->message)->toContain('already cancelled')
        ->and(EnrollmentWorkflowEvent::query()->where('event_type', 'cancelled')->count())->toBe(1);

    $this->actor->givePermissionTo(Permission::findOrCreate('Reopen:StudentEnrollment', 'web'));
    $coordinator->reopen($enrollment->refresh(), $this->actor, 'academic_verified', 'Cancellation recorded in error.', 'cancel-seat-reopen-1');

    expect($enrollment->refresh()->terminal_outcome)->toBeNull()
        ->and($enrollment->current_step_key)->toBe('academic_verified');
});

it('reverses student synchronization applied before a cancellation', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'email' => 'cancel-sync-student@example.com',
        'status' => StudentStatus::Applicant,
    ]);
    $account = Account::factory()->create([
        'email' => $student->email,
        'role' => 'guest',
        'person_id' => null,
        'person_type' => null,
        'is_active' => true,
    ]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['workflow']['steps'][0]['entry'] = false;
    array_splice($configuration['workflow']['steps'], 0, 0, [[
        'key' => 'pre_registered',
        'label' => 'Pre-registered',
        'entry' => true,
        'terminal' => false,
        'status' => StudentStatus::Applicant->value,
        'permission' => 'Update:StudentEnrollment',
        'actions' => [
            ['key' => 'subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload']],
            ['key' => 'tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
            ['key' => 'sync_student', 'handler' => 'enrollment.sync_student', 'configuration' => ['attribute' => 'status', 'value' => StudentStatus::OnLeave->value, 'sync_account' => true]],
        ],
        'transitions' => [['key' => 'staff_review', 'label' => 'Staff review', 'to' => 'submitted', 'fallback' => true, 'conditions' => []]],
    ]]);
    $configuration['workflow']['steps'][2]['transitions'] = [['key' => 'finish', 'label' => 'Finish', 'to' => 'completed', 'fallback' => true, 'conditions' => []]];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'cancel-sync-submission'));

    expect($enrollment->current_step_key)->toBe('pre_registered')
        ->and($student->refresh()->status)->toBe(StudentStatus::OnLeave)
        ->and($account->refresh()->role)->toBe('student');

    $this->actor->givePermissionTo(Permission::findOrCreate('Cancel:StudentEnrollment', 'web'));
    $coordinator->cancel($enrollment->refresh(), $this->actor, 'Pre-registration withdrawn.', 'cancel-sync-1');

    expect($enrollment->refresh()->terminal_outcome)->toBe('cancelled')
        ->and($student->refresh()->status)->toBe(StudentStatus::Applicant)
        ->and($account->refresh()->role)->toBe('guest')
        ->and($account->person_id)->toBeNull()
        ->and(data_get(EnrollmentWorkflowEvent::query()->where('event_type', 'cancelled')->latest('id')->value('result'), 'student_state_reversal.restored'))
        ->toBeTrue();
});

it('requires a reason, an authorized actor and a payment acknowledgement before cancelling', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    ($this->publishEnrollmentPolicy)(EnrollmentPolicyPreset::standard(), $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'cancel-guard-submission'));
    $this->actor->givePermissionTo(Permission::findOrCreate('Cancel:StudentEnrollment', 'web'));

    expect(fn () => $coordinator->cancel($enrollment->refresh(), $this->actor, '   ', 'cancel-guard-reason'))
        ->toThrow(EnrollmentTransitionException::class, 'reason is required');

    $unauthorized = User::factory()->create();
    expect(fn () => $coordinator->cancel($enrollment->refresh(), $unauthorized, 'No permission.', 'cancel-guard-permission'))
        ->toThrow(AuthorizationException::class, 'not allowed to cancel');
    expect($enrollment->refresh()->terminal_outcome)->toBeNull();
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'transaction_id' => Transaction::query()->create([
            'description' => 'Downpayment',
            'payment_method' => 'Cash',
            'settlements' => ['tuition_fee' => 500],
            'status' => 'Paid',
            'transaction_date' => now(),
            'invoicenumber' => 'INV-CANCEL-GUARD',
            'user_id' => $this->actor->id,
        ])->id,
        'amount' => 500,
        'status' => 'Paid',
        'idempotency_key' => 'cancel-guard-payment',
    ]);

    expect(fn () => $coordinator->cancel($enrollment->refresh(), $this->actor, 'Duplicate application.', 'cancel-guard-payments'))
        ->toThrow(EnrollmentTransitionException::class, 'still holds');
    expect($enrollment->refresh()->terminal_outcome)->toBeNull()
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->count())->toBe(1);

    $result = $coordinator->cancel(
        $enrollment->refresh(),
        $this->actor,
        'Duplicate application; refund handled in finance.',
        'cancel-guard-acknowledged',
        true,
    );

    expect($result->terminalOutcome)->toBe('cancelled')
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->count())->toBe(1)
        ->and(data_get(EnrollmentWorkflowEvent::query()->where('event_type', 'cancelled')->latest('id')->value('result'), 'outstanding_payments.amount'))
        ->toEqual(500.0)
        ->and(EnrollmentWorkflowEvent::query()->where('event_type', 'cancel_failed')->count())->toBe(1);
});

it('refuses to cancel a completed policy enrollment before it is reopened', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    ($this->publishEnrollmentPolicy)(EnrollmentPolicyPreset::standard(), $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'cancel-completed-submission'));
    $coordinator->verifyAcademic($enrollment, $this->actor, 'cancel-completed-academic');
    $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => 'INV-CANCEL-COMPLETED',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ], 'cancel-completed-payment');
    $this->actor->givePermissionTo(Permission::findOrCreate('Cancel:StudentEnrollment', 'web'));

    expect(fn () => $coordinator->cancel($enrollment->refresh(), $this->actor, 'Changed our minds.', 'cancel-completed-1'))
        ->toThrow(EnrollmentTransitionException::class, 'Reopen this enrollment before cancelling');

    expect($enrollment->refresh()->terminal_outcome)->toBe('completed')
        ->and($student->refresh()->status)->toBe(StudentStatus::Enrolled);
});

it('cancels a policy enrollment through the configured cancellation stage', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $configuration = EnrollmentPolicyPreset::standard();
    array_unshift($configuration['workflow']['steps'][1]['transitions'], [
        'key' => 'withdraw',
        'label' => 'Withdraw application',
        'to' => 'cancelled',
        'fallback' => false,
        'conditions' => [
            ['handler' => 'eligibility.duplicate_enrollment', 'configuration' => []],
        ],
    ]);
    $configuration['workflow']['steps'][] = [
        'key' => 'cancelled',
        'label' => 'Application Cancelled',
        'entry' => false,
        'terminal' => true,
        'status' => 'Cancelled',
        'outcome' => 'cancelled',
        'permission' => 'Cancel:StudentEnrollment',
        'actions' => [],
        'transitions' => [],
    ];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'cancel-stage-submission'));
    $this->actor->givePermissionTo(Permission::findOrCreate('Cancel:StudentEnrollment', 'web'));

    $coordinator->cancel($enrollment->refresh(), $this->actor, 'Applicant withdrew.', 'cancel-stage-1');

    expect($enrollment->refresh()->terminal_outcome)->toBe('cancelled')
        ->and($enrollment->current_step_key)->toBe('cancelled')
        ->and($enrollment->status)->toBe('Cancelled');
});

it('keeps preserved payments and shared class seats when a cancellation is undone', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'status' => StudentStatus::Applicant,
    ]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $class = App\Models\Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2035 - 2036',
    ]);
    ($this->publishEnrollmentPolicy)(EnrollmentPolicyPreset::standard(), $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'undo-cancel-submission'));
    $enrollment->subjectsEnrolled()->sole()->update(['class_id' => $class->id]);
    $seat = App\Models\ClassEnrollment::factory()->createOne([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    // A second enrollment record for the same student and period reuses the same class seat.
    $sibling = StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2035 - 2036',
        'semester' => 1,
        'academic_year' => 1,
        'status' => App\Enums\EnrollStat::VerifiedByDeptHead->value,
        'workflow_runtime' => StudentEnrollment::WorkflowRuntimeLegacy,
    ]);
    SubjectEnrollment::factory()->create([
        'enrollment_id' => $sibling->id,
        'subject_id' => $subject->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'school_year' => '2035 - 2036',
        'semester' => 1,
    ]);

    $transaction = Transaction::query()->create([
        'description' => 'Preserved downpayment',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
        'status' => 'Paid',
        'transaction_date' => now(),
        'invoicenumber' => 'INV-UNDO-CANCEL',
        'user_id' => $this->actor->id,
    ]);
    $link = StudentTransaction::query()->create([
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'transaction_id' => $transaction->id,
        'amount' => 500,
        'status' => 'Paid',
        'idempotency_key' => 'undo-cancel-payment',
    ]);

    $this->actor->givePermissionTo(Permission::findOrCreate('Cancel:StudentEnrollment', 'web'));
    $this->actor->givePermissionTo(Permission::findOrCreate('Reopen:StudentEnrollment', 'web'));

    $coordinator->cancel(
        $enrollment->refresh(),
        $this->actor,
        'Applicant withdrew.',
        'undo-cancel-1',
        true,
    );

    expect($seat->refresh()->status)->toBeTrue()
        ->and(data_get(EnrollmentWorkflowEvent::query()->where('event_type', 'cancelled')->latest('id')->value('result'), 'class_seats_retained.0.class_id'))
        ->toBe($class->id);

    $coordinator->reopen($enrollment->refresh(), $this->actor, null, 'Cancellation recorded in error.', 'undo-cancel-reopen-1');

    expect($enrollment->refresh()->terminal_outcome)->toBeNull()
        ->and($link->refresh()->exists)->toBeTrue()
        ->and($transaction->refresh()->exists)->toBeTrue()
        ->and(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(1)
        ->and(data_get(EnrollmentWorkflowEvent::query()->where('event_type', 'reopened')->latest('id')->value('result'), 'payment_reversal.skipped'))
        ->toBeTrue();
});

it('still reverses payments when reopening a completed transition', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    ($this->publishEnrollmentPolicy)(EnrollmentPolicyPreset::standard(), $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'undo-completed-submission'));
    $coordinator->verifyAcademic($enrollment, $this->actor, 'undo-completed-academic');
    $coordinator->verifyPayment($enrollment->refresh(), $this->actor, [
        'invoicenumber' => 'INV-UNDO-COMPLETED',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
    ], 'undo-completed-payment');
    $this->actor->givePermissionTo(Permission::findOrCreate('Reopen:StudentEnrollment', 'web'));

    $coordinator->reopen($enrollment->refresh(), $this->actor, null, 'Cashier correction.', 'undo-completed-reopen-1');

    expect(StudentTransaction::query()->where('student_enrollment_id', $enrollment->id)->exists())->toBeFalse()
        ->and(Transaction::query()->count())->toBe(0)
        ->and(data_get(EnrollmentWorkflowEvent::query()->where('event_type', 'reopened')->latest('id')->value('result'), 'payment_reversal.skipped'))
        ->toBeNull();
});

it('blocks completion using saved curriculum units and rolls back the transition', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'lecture' => 3, 'laboratory' => 2]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['rules'][] = ['key' => 'maximum_units', 'handler' => 'academics.maximum_assigned_units', 'configuration' => ['maximum' => 4]];
    $configuration['workflow']['steps'][2]['actions'] = [];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'trusted-unit-gate'));
    $coordinator->verifyAcademic($enrollment, $this->actor, 'trusted-unit-academic');

    expect(fn () => $coordinator->transition($enrollment->fresh(), $this->actor, null, [], 'trusted-unit-complete'))
        ->toThrow(EnrollmentTransitionException::class, 'Assigned units exceed');
    expect($enrollment->refresh()->current_step_key)->toBe('academic_verified');
    expect($enrollment->terminal_outcome)->toBeNull();
});

it('enforces record limits on terminal entry workflows and rolls back submission', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'lecture' => 3, 'laboratory' => 2]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['rules'][] = ['key' => 'maximum_units', 'handler' => 'academics.maximum_assigned_units', 'configuration' => ['maximum' => 4]];
    $entry = $configuration['workflow']['steps'][0];
    $entry['terminal'] = true;
    $entry['outcome'] = 'completed';
    $entry['transitions'] = [];
    $configuration['workflow']['steps'] = [$entry];
    ($this->publishEnrollmentPolicy)($configuration, $school);

    expect(fn () => app(EnrollmentWorkflowCoordinator::class)->submit(($this->submissionFor)($student, $subject, 'terminal-entry-unit-gate')))
        ->toThrow(EnrollmentTransitionException::class, 'Assigned units exceed');
    $this->assertDatabaseMissing('student_enrollment', ['student_id' => $student->id]);
    $this->assertDatabaseMissing('subject_enrollments', ['student_id' => $student->id]);
});

it('blocks completion when saved active sections overlap', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id]);
    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['rules'][] = ['key' => 'verified_schedule', 'handler' => 'academics.verified_schedule', 'configuration' => []];
    $configuration['workflow']['steps'][2]['actions'] = [];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'saved-schedule-gate'));
    foreach (['09:00', '09:30'] as $start) {
        $class = App\Models\Classes::factory()->create(['school_id' => $school->id, 'school_year' => $enrollment->school_year, 'semester' => $enrollment->semester]);
        App\Models\ClassEnrollment::query()->create(['student_id' => $student->id, 'class_id' => $class->id, 'status' => true]);
        App\Models\Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Monday', 'start_time' => $start, 'end_time' => '11:00']);
    }
    $coordinator->verifyAcademic($enrollment, $this->actor, 'saved-schedule-academic');

    expect(fn () => $coordinator->transition($enrollment->fresh(), $this->actor, null, [], 'saved-schedule-completion'))
        ->toThrow(EnrollmentTransitionException::class, 'Assigned sections overlap');
    expect($enrollment->refresh()->current_step_key)->toBe('academic_verified');
    expect($enrollment->terminal_outcome)->toBeNull();
});

it('blocks completion when verified prerequisite history is missing', function (): void {
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id]);
    $required = Subject::factory()->create(['course_id' => $course->id]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'pre_riquisite' => [$required->id]]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['rules'][] = ['key' => 'saved_prerequisites', 'handler' => 'academics.verified_prerequisites', 'configuration' => []];
    $configuration['workflow']['steps'][2]['actions'] = [];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'saved-prerequisite-gate'));
    $coordinator->verifyAcademic($enrollment, $this->actor, 'saved-prerequisite-academic');

    expect(fn () => $coordinator->transition($enrollment->fresh(), $this->actor, null, [], 'saved-prerequisite-completion'))
        ->toThrow(EnrollmentTransitionException::class, 'Required prerequisite subjects');
    expect($enrollment->refresh()->current_step_key)->toBe('academic_verified');
    expect($enrollment->terminal_outcome)->toBeNull();
});

it('dispatches configured student inbox notifications once after a transition', function (): void {
    NotificationFacade::fake();
    $school = School::factory()->create();
    $course = App\Models\Course::factory()->create(['school_id' => $school->id]);
    $portalUser = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'user_id' => $portalUser->id, 'email' => null]);
    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $configuration = EnrollmentPolicyPreset::standard();
    $configuration['notifications'] = [['event' => 'academic_verified', 'channel' => 'database', 'enabled' => true]];
    ($this->publishEnrollmentPolicy)($configuration, $school);
    $coordinator = app(EnrollmentWorkflowCoordinator::class);
    $enrollment = $coordinator->submit(($this->submissionFor)($student, $subject, 'inbox-submission'));

    $coordinator->verifyAcademic($enrollment, $this->actor, 'inbox-academic');
    $coordinator->verifyAcademic($enrollment->fresh(), $this->actor, 'inbox-academic');

    NotificationFacade::assertSentToTimes($portalUser, App\Notifications\EnrollmentDatabaseNotification::class, 1);
});
