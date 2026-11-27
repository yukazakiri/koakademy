<?php

declare(strict_types=1);

use App\Ai\Tools\AuditStudentProfileImportTool;
use App\Models\School;
use App\Models\Student;
use App\Services\StudentConfigurationService;
use App\Services\TenantContext;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
    $this->school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($this->school);
});

/**
 * Helper to apply one of the built-in external identifier presets to the current school.
 *
 * @param  array<string, mixed>  $externalOverrides
 * @param  array<int, string>|null  $enabledTypes
 */
function configureSchoolExternalPreset(string $presetKey, array $externalOverrides = [], ?array $enabledTypes = null): void
{
    $presets = StudentConfigurationService::externalIdentifierPresets();
    $config = StudentConfigurationService::defaults();
    $config['external_identifier'] = array_replace(
        $config['external_identifier'],
        $presets[$presetKey]['config'],
        $externalOverrides,
    );

    if ($enabledTypes !== null) {
        $config['enabled_types'] = $enabledTypes;
    }

    app(StudentConfigurationService::class)->update($config);
}

it('does not falsely flag missing or non-12-digit external IDs in neutral mode while still catching email and student ID anomalies', function (): void {
    configureSchoolExternalPreset('none');

    Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'student_id' => '2026-0001',
        'email' => 'existing@school.edu',
    ]);

    $tool = new AuditStudentProfileImportTool;
    $data = json_decode((string) $tool->handle(new Request([
        'profiles' => [
            [
                'first_name' => 'Amina',
                'last_name' => 'Okafor',
                'external_identifier' => 'SHORT-1',
                'student_id' => '2026-0002',
                'email' => 'amina@school.edu',
            ],
            [
                'first_name' => 'Liam',
                'last_name' => 'Chen',
                'email' => 'not-an-email',
            ],
            [
                'first_name' => 'Sofia',
                'last_name' => 'Martinez',
                'lrn' => 'ABC99',
                'email' => 'EXISTING@school.edu',
            ],
            [
                'first_name' => 'Noah',
                'last_name' => 'Kim',
                'student_id' => '2026-0001',
                'email' => 'amina@school.edu',
            ],
        ],
    ])), true);

    expect($data['total_rows_audited'])->toBe(4)
        ->and($data['clean_rows_count'])->toBe(1)
        ->and($data['anomaly_count'])->toBe(4)
        ->and($data['is_ready_for_import'])->toBeFalse()
        ->and($data['external_identifier_policy']['enabled'])->toBeFalse()
        ->and($data['external_identifier_policy']['label'])->toBe('External Identifier');

    $issues = array_column($data['anomalies'], 'issue');

    expect($issues)->toContain("Invalid email address 'not-an-email'.")
        ->and($issues)->toContain("Duplicate email address 'EXISTING@school.edu' already exists in active student directory.")
        ->and($issues)->toContain("Duplicate email address 'amina@school.edu' also appears on row 1 of this import batch.")
        ->and($issues)->toContain("Duplicate Student ID '2026-0001' already exists in active student directory.");

    foreach ($issues as $issue) {
        expect($issue)->not->toContain('LRN')
            ->and($issue)->not->toContain('External Identifier');
    }
});

it('validates UK ULN format and flags directory and within-batch duplicates', function (): void {
    configureSchoolExternalPreset('uk_uln');

    Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'lrn' => '1234567890',
    ]);

    $tool = new AuditStudentProfileImportTool;
    $data = json_decode((string) $tool->handle(new Request([
        'profiles' => [
            [
                'first_name' => 'Oliver',
                'last_name' => 'Smith',
                'student_type' => 'college',
                'external_identifier' => '9876543210',
                'email' => 'oliver@uk.ac.uk',
            ],
            [
                'first_name' => 'Amelia',
                'last_name' => 'Jones',
                'student_type' => 'college',
                'external_identifier' => '123456789012',
                'email' => 'amelia@uk.ac.uk',
            ],
            [
                'first_name' => 'George',
                'last_name' => 'Brown',
                'student_type' => 'college',
                'external_identifier' => '1234567890',
                'email' => 'george@uk.ac.uk',
            ],
            [
                'first_name' => 'Isla',
                'last_name' => 'Taylor',
                'student_type' => 'college',
                'external_identifier' => '9876543210',
                'email' => 'isla@uk.ac.uk',
            ],
        ],
    ])), true);

    expect($data['total_rows_audited'])->toBe(4)
        ->and($data['clean_rows_count'])->toBe(1)
        ->and($data['anomaly_count'])->toBe(3)
        ->and($data['is_ready_for_import'])->toBeFalse()
        ->and($data['external_identifier_policy'])->toMatchArray([
            'enabled' => true,
            'label' => 'ULN',
            'format' => 'digits',
            'min_length' => 10,
            'max_length' => 10,
        ]);

    expect($data['anomalies'][0]['row'])->toBe(2)
        ->and($data['anomalies'][0]['issue'])->toContain("Invalid ULN '123456789012'")
        ->and($data['anomalies'][0]['issue'])->toContain('exactly 10 numeric digits')
        ->and($data['anomalies'][1]['row'])->toBe(3)
        ->and($data['anomalies'][1]['issue'])->toContain("Duplicate ULN '1234567890' already exists in active student directory.")
        ->and($data['anomalies'][1]['severity'])->toBe('Critical Duplicate')
        ->and($data['anomalies'][2]['row'])->toBe(4)
        ->and($data['anomalies'][2]['issue'])->toContain("Duplicate ULN '9876543210' also appears on row 1")
        ->and($data['anomalies'][2]['severity'])->toBe('Critical Duplicate');
});

it('validates Australia USI alphanumeric identifiers via external_identifier', function (): void {
    configureSchoolExternalPreset('au_usi');

    $tool = new AuditStudentProfileImportTool;
    $data = json_decode((string) $tool->handle(new Request([
        'profiles' => [
            [
                'first_name' => 'Chloe',
                'last_name' => 'Wilson',
                'student_type' => 'college',
                'external_identifier' => '3AW88YH9U5',
                'email' => 'chloe@tafe.edu.au',
            ],
            [
                'first_name' => 'Jack',
                'last_name' => 'Thompson',
                'student_type' => 'college',
                'external_identifier' => '3AW-88YH9U',
                'email' => 'jack@tafe.edu.au',
            ],
        ],
    ])), true);

    expect($data['total_rows_audited'])->toBe(2)
        ->and($data['clean_rows_count'])->toBe(1)
        ->and($data['anomaly_count'])->toBe(1)
        ->and($data['is_ready_for_import'])->toBeFalse()
        ->and($data['external_identifier_policy'])->toMatchArray([
            'enabled' => true,
            'label' => 'USI',
            'format' => 'alphanumeric',
            'min_length' => 10,
            'max_length' => 10,
        ])
        ->and($data['anomalies'][0]['row'])->toBe(2)
        ->and($data['anomalies'][0]['issue'])->toContain("Invalid USI '3AW-88YH9U'")
        ->and($data['anomalies'][0]['issue'])->toContain('exactly 10 alphanumeric characters');
});

it('enforces Philippines LRN preset for SHS rows including required presence and duplicates without flagging college rows', function (): void {
    configureSchoolExternalPreset('ph_lrn', ['required' => true], ['college', 'shs']);

    Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'lrn' => '123456789012',
    ]);

    $tool = new AuditStudentProfileImportTool;
    $data = json_decode((string) $tool->handle(new Request([
        'profiles' => [
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'student_type' => 'shs',
                'lrn' => '109876543210',
                'email' => 'juan@school.edu.ph',
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'student_type' => 'shs',
                'email' => 'maria@school.edu.ph',
            ],
            [
                'first_name' => 'Jose',
                'last_name' => 'Rizal',
                'student_type' => 'shs',
                'lrn' => '123456789012',
                'email' => 'jose@school.edu.ph',
            ],
            [
                'first_name' => 'Clara',
                'last_name' => 'Mendoza',
                'student_type' => 'college',
                'email' => 'clara@school.edu.ph',
            ],
        ],
    ])), true);

    expect($data['total_rows_audited'])->toBe(4)
        ->and($data['clean_rows_count'])->toBe(2)
        ->and($data['anomaly_count'])->toBe(2)
        ->and($data['is_ready_for_import'])->toBeFalse()
        ->and($data['external_identifier_policy'])->toMatchArray([
            'enabled' => true,
            'label' => 'LRN',
            'format' => 'digits',
            'min_length' => 12,
            'max_length' => 12,
        ]);

    expect($data['anomalies'][0]['row'])->toBe(2)
        ->and($data['anomalies'][0]['issue'])->toContain('Missing required LRN')
        ->and($data['anomalies'][1]['row'])->toBe(3)
        ->and($data['anomalies'][1]['issue'])->toContain("Duplicate LRN '123456789012' already exists in active student directory.");
});

it('never reports a negative clean_rows_count when a single row has multiple anomalies', function (): void {
    configureSchoolExternalPreset('uk_uln');

    Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'student_id' => 'UK-9001',
    ]);

    $tool = new AuditStudentProfileImportTool;
    $data = json_decode((string) $tool->handle(new Request([
        'profiles' => [
            [
                'first_name' => 'Broken',
                'last_name' => 'Record',
                'student_type' => 'college',
                'external_identifier' => 'NOT-A-ULN',
                'student_id' => 'UK-9001',
                'email' => 'broken-email',
            ],
        ],
    ])), true);

    expect($data['total_rows_audited'])->toBe(1)
        ->and($data['anomaly_count'])->toBe(3)
        ->and($data['clean_rows_count'])->toBe(0)
        ->and($data['is_ready_for_import'])->toBeFalse();
});
