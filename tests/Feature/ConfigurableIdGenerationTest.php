<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\IdSequence;
use App\Models\Student;
use App\Models\User;
use App\Services\IdentifierGenerator;

it('previews and consumes the configured student sequence', function (): void {
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 200123,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStudentId())->toBe(200123)
        ->and(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(200123)
        ->and($generator->generateStudentId())->toBe(200123)
        ->and(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(200124);
});

it('previews and consumes the shared staff sequence for faculty identifiers', function (): void {
    IdSequence::query()->create([
        'key' => 'staff',
        'label' => 'Staff IDs',
        'start_number' => 800000,
        'next_number' => 800000,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStaffId())->toBe('800000')
        ->and(IdSequence::query()->where('key', 'staff')->value('next_number'))->toBe(800000)
        ->and($generator->generateStaffId())->toBe('800000')
        ->and($generator->generateStaffId())->toBe('800001')
        ->and(IdSequence::query()->where('key', 'staff')->value('next_number'))->toBe(800002);
});

it('uses the configured student sequence in the administrator generated id endpoint', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 200555,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $this->actingAs($admin)
        ->getJson(route('administrators.students.generate-id', ['type' => StudentType::College->value]))
        ->assertSuccessful()
        ->assertJson(['id' => 200555]);

    expect(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(200555);
});

it('does not generate a configurable student ID for SHS because SHS uses LRN', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 200555,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $this->actingAs($admin)
        ->getJson(route('administrators.students.generate-id', ['type' => StudentType::SeniorHighSchool->value]))
        ->assertSuccessful()
        ->assertJson(['id' => null]);

    expect(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(200555);
});

it('previews the shared staff sequence on the faculty create page', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    IdSequence::query()->create([
        'key' => 'staff',
        'label' => 'Staff IDs',
        'start_number' => 800000,
        'next_number' => 800777,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $this->actingAs($admin)
        ->get(route('administrators.faculties.create'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/faculties/create', false)
            ->where('defaults.faculty_id_number', '800777'));
});

it('consumes the shared staff sequence when creating faculty with the previewed identifier', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    IdSequence::query()->create([
        'key' => 'staff',
        'label' => 'Staff IDs',
        'start_number' => 800000,
        'next_number' => 800900,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.store'), [
            'faculty_id_number' => '800900',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada.lovelace@example.test',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(Faculty::query()->where('email', 'ada.lovelace@example.test')->value('faculty_id_number'))->toBe('800900')
        ->and(IdSequence::query()->where('key', 'staff')->value('next_number'))->toBe(800901);
});

it('renders identifier sequence settings in system management', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Developer]);
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 200010,
        'increment_by' => 1,
        'padding' => 6,
    ]);
    IdSequence::query()->create([
        'key' => 'staff',
        'label' => 'Staff IDs',
        'start_number' => 800000,
        'next_number' => 800010,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $this->actingAs($admin)
        ->get(route('administrators.system-management.identifiers.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/system-management/identifiers', false)
            ->where('id_sequences.student.next_number', 200010)
            ->where('id_sequences.staff.next_number', 800010));
});

it('updates identifier sequence settings from system management', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Developer]);

    $this->actingAs($admin)
        ->put(route('administrators.system-management.identifiers.update'), [
            'student' => [
                'start_number' => 210000,
                'next_number' => 210000,
                'increment_by' => 1,
                'padding' => 6,
            ],
            'staff' => [
                'start_number' => 800000,
                'next_number' => 800500,
                'increment_by' => 1,
                'padding' => 6,
            ],
        ])
        ->assertRedirect();

    expect(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(210000)
        ->and(IdSequence::query()->where('key', 'staff')->value('next_number'))->toBe(800500);
});

it('bumps the configured student sequence above existing student records', function (): void {
    Student::factory()->create(['student_id' => 200999]);
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 200100,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStudentId())->toBe(201000)
        ->and(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(201000)
        ->and($generator->generateStudentId())->toBe(201000)
        ->and(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(201001);
});

it('ignores SHS LRN values when adapting the generated student ID sequence', function (): void {
    Student::factory()->create([
        'student_id' => 220792,
        'student_type' => StudentType::College->value,
    ]);
    Student::factory()->create([
        'student_id' => 102004140011,
        'lrn' => '102004140011',
        'student_type' => StudentType::SeniorHighSchool->value,
    ]);
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 200100,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStudentId())->toBe(220793)
        ->and(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(220793);
});

it('repairs a generated student sequence that was polluted by an SHS LRN value', function (): void {
    Student::factory()->create([
        'student_id' => 668670,
        'student_type' => StudentType::College->value,
    ]);
    Student::factory()->create([
        'student_id' => 102004140011,
        'lrn' => '102004140011',
        'student_type' => StudentType::SeniorHighSchool->value,
    ]);
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 102004140012,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStudentId())->toBe(668671)
        ->and(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(668671);
});

it('uses the latest-created non-SHS student ID instead of an older high numeric outlier', function (): void {
    Student::factory()->create([
        'student_id' => 668670,
        'student_type' => StudentType::College->value,
        'created_at' => now()->subYear(),
    ]);
    Student::factory()->create([
        'student_id' => 208424,
        'student_type' => StudentType::College->value,
        'created_at' => now(),
    ]);
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 668671,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStudentId())->toBe(208425)
        ->and(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(208425);
});

it('bumps the shared staff sequence above existing numeric faculty records', function (): void {
    Faculty::factory()->create(['faculty_id_number' => '800999']);
    Faculty::factory()->create(['faculty_id_number' => 'FAC-999999']);
    IdSequence::query()->create([
        'key' => 'staff',
        'label' => 'Staff IDs',
        'start_number' => 800000,
        'next_number' => 800100,
        'increment_by' => 1,
        'padding' => 6,
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStaffId())->toBe('801000')
        ->and(IdSequence::query()->where('key', 'staff')->value('next_number'))->toBe(801000)
        ->and($generator->generateStaffId())->toBe('801000')
        ->and(IdSequence::query()->where('key', 'staff')->value('next_number'))->toBe(801001);
});

it('configures custom static prefix and previews formatted student ID', function (): void {
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 1,
        'next_number' => 1,
        'increment_by' => 1,
        'padding' => 4,
        'prefix_mode' => 'static',
        'prefix_value' => '2026',
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStudentId())->toBe(20260001)
        ->and($generator->generateStudentId())->toBe(20260001)
        ->and(IdSequence::query()->where('key', 'student')->value('next_number'))->toBe(2);
});

it('configures year-based prefix mode and generates formatted student ID', function (): void {
    $currentYear = (int) date('Y');
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 1,
        'next_number' => 55,
        'increment_by' => 1,
        'padding' => 4,
        'prefix_mode' => 'year',
    ]);

    $generator = app(IdentifierGenerator::class);
    $expected = (int) ($currentYear.'0055');

    expect($generator->previewStudentId())->toBe($expected)
        ->and($generator->generateStudentId())->toBe($expected);
});

it('configures per-student-type prefixes and generates accordingly', function (): void {
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 1,
        'next_number' => 10,
        'increment_by' => 1,
        'padding' => 5,
        'prefix_mode' => 'by_type',
        'type_prefixes' => [
            'college' => '1',
            'tesda' => '5',
            'dhrt' => '7',
            'shs' => '3',
        ],
    ]);

    $generator = app(IdentifierGenerator::class);

    expect($generator->previewStudentId(StudentType::College))->toBe(100010)
        ->and($generator->previewStudentId(StudentType::TESDA))->toBe(500010)
        ->and($generator->previewStudentId(StudentType::DHRT))->toBe(700010);
});

it('allows flexible digit lengths in validation when enforce_length is false', function (): void {
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 200000,
        'increment_by' => 1,
        'padding' => 6,
        'enforce_length' => false,
        'min_length' => 4,
        'max_length' => 12,
    ]);

    $generator = app(IdentifierGenerator::class);
    $rules = $generator->getStudentIdValidationRules(StudentType::College);

    expect($rules)->toContain('numeric')
        ->and($rules)->toContain('digits_between:4,12');
});

it('enforces prefix in student validation rules only when enforce_prefix is true', function (): void {
    IdSequence::query()->create([
        'key' => 'student',
        'label' => 'Student IDs',
        'start_number' => 200000,
        'next_number' => 200000,
        'increment_by' => 1,
        'padding' => 6,
        'prefix_mode' => 'static',
        'prefix_value' => '2026',
        'enforce_prefix' => true,
    ]);

    $generator = app(IdentifierGenerator::class);
    $rules = $generator->getStudentIdValidationRules(StudentType::College);

    // There should be a closure rule for prefix check
    $hasClosure = collect($rules)->contains(fn ($rule) => $rule instanceof Closure);
    expect($hasClosure)->toBeTrue();
});

it('updates full configurable identifier settings from system management', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Developer]);

    $this->actingAs($admin)
        ->put(route('administrators.system-management.identifiers.update'), [
            'student' => [
                'start_number' => 20260001,
                'next_number' => 20260001,
                'increment_by' => 1,
                'padding' => 8,
                'prefix_mode' => 'static',
                'prefix_value' => '2026',
                'enforce_prefix' => true,
                'enforce_length' => true,
                'exact_length' => 8,
                'min_length' => 4,
                'max_length' => 12,
                'type_prefixes' => [
                    'college' => '2026',
                    'tesda' => '2026',
                    'dhrt' => '2026',
                    'shs' => '3',
                ],
            ],
            'staff' => [
                'start_number' => 800000,
                'next_number' => 800500,
                'increment_by' => 1,
                'padding' => 6,
            ],
        ])
        ->assertRedirect();

    $studentSeq = IdSequence::query()->where('key', 'student')->first();
    expect($studentSeq->next_number)->toBe(20260001)
        ->and($studentSeq->padding)->toBe(8)
        ->and($studentSeq->prefix_mode)->toBe('static')
        ->and($studentSeq->prefix_value)->toBe('2026')
        ->and($studentSeq->enforce_prefix)->toBeTrue()
        ->and($studentSeq->enforce_length)->toBeTrue()
        ->and($studentSeq->exact_length)->toBe(8);
});
