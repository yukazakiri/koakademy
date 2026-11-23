<?php

declare(strict_types=1);

use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\GeneralSetting;
use App\Models\Schedule;
use App\Models\StudentEnrollment;
use App\Services\GeneralSettingsService;

it('checks saved schedules using normalized days and configurable travel gaps', function (string $day, string $start, int $gap, bool $passed): void {
    $enrollment = StudentEnrollment::factory()->create(['school_year' => '2035 - 2036', 'semester' => 1]);
    foreach ([['Monday', '09:00', '10:00'], [$day, $start, '11:00']] as [$scheduleDay, $scheduleStart, $scheduleEnd]) {
        $class = Classes::factory()->create(['school_id' => $enrollment->school_id, 'school_year' => '2035-2036', 'semester' => 1]);
        ClassEnrollment::query()->create(['student_id' => $enrollment->student_id, 'class_id' => $class->id, 'status' => true]);
        Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => $scheduleDay, 'start_time' => $scheduleStart, 'end_time' => $scheduleEnd]);
    }
    $context = EnrollmentContext::fromEnrollment($enrollment, facts: ['eligibility_schedule_conflict' => true]);

    $result = app(EnrollmentPolicyRegistry::class)->rule('academics.verified_schedule')->evaluate($context, ['minimum_gap_minutes' => $gap]);

    expect($result->passed)->toBe($passed);
    expect($result->metadata['checked_classes'])->toBe(2);
})->with([
    'overlap normalized abbreviation' => ['mon', '09:30', 0, false],
    'back to back allowed' => ['Monday', '10:00', 0, true],
    'travel gap required' => ['Monday', '10:00', 15, false],
    'exact gap allowed' => ['Monday', '10:15', 15, true],
    'different days' => ['Tuesday', '09:30', 15, true],
]);

it('handles unscheduled sections only through explicit missing schedule policy', function (string $policy, bool $passed): void {
    $enrollment = StudentEnrollment::factory()->create(['school_year' => '2035 - 2036', 'semester' => 1]);
    $class = Classes::factory()->create(['school_id' => $enrollment->school_id, 'school_year' => '2035 - 2036', 'semester' => 1]);
    ClassEnrollment::query()->create(['student_id' => $enrollment->student_id, 'class_id' => $class->id, 'status' => true]);

    $result = app(EnrollmentPolicyRegistry::class)->rule('academics.verified_schedule')->evaluate(EnrollmentContext::fromEnrollment($enrollment), ['missing_schedule' => $policy]);

    expect($result->passed)->toBe($passed);
    expect($result->metadata['unchecked_class_ids'])->toBe([$class->id]);
})->with(['default blocks' => ['block', false], 'explicit exception' => ['allow', true]]);

it('ignores inactive roster rows and classes outside the enrollment period', function (): void {
    $enrollment = StudentEnrollment::factory()->create(['school_year' => '2035 - 2036', 'semester' => 1]);
    foreach ([[1, true], [1, false], [2, true]] as [$semester, $active]) {
        $class = Classes::factory()->create(['school_id' => $enrollment->school_id, 'school_year' => '2035 - 2036', 'semester' => $semester]);
        ClassEnrollment::query()->create(['student_id' => $enrollment->student_id, 'class_id' => $class->id, 'status' => $active]);
        Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Monday', 'start_time' => '09:00', 'end_time' => '10:00']);
    }

    $result = app(EnrollmentPolicyRegistry::class)->rule('academics.verified_schedule')->evaluate(EnrollmentContext::fromEnrollment($enrollment), []);

    expect($result->passed)->toBeTrue();
    expect($result->metadata['checked_classes'])->toBe(1);
});

it('fails closed for invalid days and overnight schedules', function (string $day, string $start, string $end): void {
    $enrollment = StudentEnrollment::factory()->create(['school_year' => '2035 - 2036', 'semester' => 1]);
    $class = Classes::factory()->create(['school_id' => $enrollment->school_id, 'school_year' => $enrollment->school_year, 'semester' => 1]);
    ClassEnrollment::query()->create(['student_id' => $enrollment->student_id, 'class_id' => $class->id, 'status' => true]);
    Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => $day, 'start_time' => $start, 'end_time' => $end]);

    $result = app(EnrollmentPolicyRegistry::class)->rule('academics.verified_schedule')->evaluate(EnrollmentContext::fromEnrollment($enrollment), []);

    expect($result->passed)->toBeFalse();
    expect($result->metadata['unchecked_class_ids'])->toBe([$class->id]);
})->with(['unknown day' => ['TBA', '09:00', '10:00'], 'overnight unsupported' => ['Monday', '23:00', '01:00']]);

it('excludes other-school sections and fails closed without active classes', function (): void {
    $enrollment = StudentEnrollment::factory()->create(['school_year' => '2035 - 2036', 'semester' => 1]);
    $foreignSchool = App\Models\School::factory()->create();
    $class = Classes::factory()->create(['school_id' => $foreignSchool->id, 'school_year' => $enrollment->school_year, 'semester' => 1]);
    ClassEnrollment::query()->create(['student_id' => $enrollment->student_id, 'class_id' => $class->id, 'status' => true]);

    $result = app(EnrollmentPolicyRegistry::class)->rule('academics.verified_schedule')->evaluate(EnrollmentContext::fromEnrollment($enrollment), []);

    expect($result->passed)->toBeFalse();
    expect($result->metadata['checked_classes'])->toBe(0);
});

it('rejects unsupported schedule policy configuration before publication', function (array $settings): void {
    $configuration = App\Enrollment\EnrollmentPolicyPreset::standard();
    $configuration['rules'][] = ['key' => 'saved_schedule', 'handler' => 'academics.verified_schedule', 'configuration' => $settings];

    expect(fn () => app(App\Enrollment\EnrollmentPolicyCompiler::class)->compile([['version_id' => 1, 'configuration' => $configuration]]))
        ->toThrow(Illuminate\Validation\ValidationException::class);
})->with([
    'negative gap' => [['minimum_gap_minutes' => -1]],
    'unknown missing mode' => [['missing_schedule' => 'ignore_everything']],
]);

it('skips asynchronous online classes from schedule overlap and missing-schedule checks by default', function (): void {
    $enrollment = StudentEnrollment::factory()->create(['school_year' => '2035 - 2036', 'semester' => 1]);
    $campus = Classes::factory()->create([
        'school_id' => $enrollment->school_id,
        'school_year' => '2035 - 2036',
        'semester' => 1,
        'delivery_mode' => 'face_to_face',
    ]);
    $asyncWithOverlap = Classes::factory()->create([
        'school_id' => $enrollment->school_id,
        'school_year' => '2035 - 2036',
        'semester' => 1,
        'delivery_mode' => 'online',
    ]);
    $asyncUnscheduled = Classes::factory()->create([
        'school_id' => $enrollment->school_id,
        'school_year' => '2035 - 2036',
        'semester' => 1,
        'delivery_mode' => 'online',
    ]);

    foreach ([$campus, $asyncWithOverlap, $asyncUnscheduled] as $class) {
        ClassEnrollment::query()->create(['student_id' => $enrollment->student_id, 'class_id' => $class->id, 'status' => true]);
    }

    Schedule::factory()->create(['class_id' => $campus->id, 'delivery_mode' => 'face_to_face', 'day_of_week' => 'Monday', 'start_time' => '09:00', 'end_time' => '10:00']);
    Schedule::factory()->create(['class_id' => $asyncWithOverlap->id, 'delivery_mode' => 'online', 'day_of_week' => 'Monday', 'start_time' => '09:30', 'end_time' => '10:30']);

    $result = app(EnrollmentPolicyRegistry::class)->rule('academics.verified_schedule')->evaluate(EnrollmentContext::fromEnrollment($enrollment), []);

    expect($result->passed)->toBeTrue()
        ->and($result->metadata['unchecked_class_ids'])->toBe([]);
});

it('enforces schedule overlap checks for synchronous online classes and honors configurable scheduling policy', function (): void {
    GeneralSetting::factory()->create(['school_starting_date' => '2035-06-01', 'school_ending_date' => '2036-03-31', 'semester' => 1]);
    $enrollment = StudentEnrollment::factory()->create(['school_year' => '2035 - 2036', 'semester' => 1]);
    $campus = Classes::factory()->create(['school_id' => $enrollment->school_id, 'school_year' => '2035 - 2036', 'semester' => 1, 'delivery_mode' => 'face_to_face']);
    $syncOnline = Classes::factory()->create(['school_id' => $enrollment->school_id, 'school_year' => '2035 - 2036', 'semester' => 1, 'delivery_mode' => 'online_synchronous']);

    ClassEnrollment::query()->create(['student_id' => $enrollment->student_id, 'class_id' => $campus->id, 'status' => true]);
    ClassEnrollment::query()->create(['student_id' => $enrollment->student_id, 'class_id' => $syncOnline->id, 'status' => true]);

    Schedule::factory()->create(['class_id' => $campus->id, 'delivery_mode' => 'face_to_face', 'day_of_week' => 'Monday', 'start_time' => '09:00', 'end_time' => '10:00']);
    Schedule::factory()->create(['class_id' => $syncOnline->id, 'delivery_mode' => 'online_synchronous', 'day_of_week' => 'Monday', 'start_time' => '09:30', 'end_time' => '10:30']);

    $rule = app(EnrollmentPolicyRegistry::class)->rule('academics.verified_schedule');
    $blocked = $rule->evaluate(EnrollmentContext::fromEnrollment($enrollment), []);
    expect($blocked->passed)->toBeFalse();

    app(GeneralSettingsService::class)->updateSchedulingConfig(['check_synchronous_online_conflicts' => false]);

    $allowed = $rule->evaluate(EnrollmentContext::fromEnrollment($enrollment), []);
    expect($allowed->passed)->toBeTrue();
});
