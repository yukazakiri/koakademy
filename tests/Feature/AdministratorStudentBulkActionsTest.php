<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Mail\StudentBulkMessage;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentStatusRecord;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);
});

it('bulk updates student status', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $students = Student::factory()->count(2)->create([
        'status' => StudentStatus::Applicant,
    ]);

    actingAs($user)
        ->patch(route('administrators.students.bulk-update-status'), [
            'student_ids' => $students->pluck('id')->all(),
            'status' => StudentStatus::Enrolled->value,
        ])
        ->assertRedirect();

    $settingsService = app(GeneralSettingsService::class);
    $currentYear = $settingsService->getCurrentSchoolYearString();
    $currentSemester = $settingsService->getCurrentSemester();

    foreach ($students as $student) {
        $record = StudentStatusRecord::query()
            ->where('student_id', $student->id)
            ->where('academic_year', $currentYear)
            ->where('semester', $currentSemester)
            ->first();

        expect($student->refresh()->status)->toBe(StudentStatus::Enrolled)
            ->and($record)->not->toBeNull()
            ->and($record?->status)->toBe(StudentStatus::Enrolled);
    }
});

it('bulk updates student clearance for current semester', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $students = Student::factory()->count(2)->create();

    actingAs($user)
        ->post(route('administrators.students.bulk-manage-clearance'), [
            'student_ids' => $students->pluck('id')->all(),
            'is_cleared' => true,
        ])
        ->assertRedirect();

    $settingsService = app(GeneralSettingsService::class);
    $currentYear = $settingsService->getCurrentSchoolYearString();
    $currentSemester = $settingsService->getCurrentSemester();

    foreach ($students as $student) {
        $clearance = StudentClearance::query()
            ->where('student_id', $student->id)
            ->where('academic_year', $currentYear)
            ->where('semester', $currentSemester)
            ->first();

        expect($clearance)->not->toBeNull()
            ->and($clearance?->is_cleared)->toBeTrue();
    }
});

it('bulk soft deletes students', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $students = Student::factory()->count(2)->create();

    actingAs($user)
        ->delete(route('administrators.students.bulk-destroy'), [
            'student_ids' => $students->pluck('id')->all(),
        ])
        ->assertRedirect();

    $deletedStudents = Student::withTrashed()->whereIn('id', $students->pluck('id')->all())->get();

    expect($deletedStudents->count())->toBe(2)
        ->and($deletedStudents->every(fn (Student $student): bool => $student->trashed()))->toBeTrue();
});

it('bulk sends a formal email to students with addresses', function (): void {
    Mail::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $students = Student::factory()->count(2)->create([
        'email' => null,
    ]);

    $students->first()->update(['email' => 'student@example.com']);

    actingAs($user)
        ->post(route('administrators.students.bulk-email'), [
            'student_ids' => $students->pluck('id')->all(),
            'subject' => 'Important Update',
            'message' => 'Please review your records.',
        ])
        ->assertRedirect();

    Mail::assertSent(StudentBulkMessage::class, 1);
});
