<?php

declare(strict_types=1);

use App\Support\RegistrarStudentProfileWorkbook;
use Illuminate\Support\Facades\Schema;

it('caches available schema columns while resolving multiple profile targets', function (): void {
    Schema::shouldReceive('getColumnListing')
        ->once()
        ->with('students')
        ->andReturn(['phone', 'nationality', 'contacts']);
    Schema::shouldReceive('getColumnListing')
        ->once()
        ->with('student_contacts')
        ->andReturn(['personal_contact']);
    Schema::shouldReceive('getColumnListing')
        ->once()
        ->with('students_personal_info')
        ->andReturn(['citizenship']);

    $workbook = new RegistrarStudentProfileWorkbook;

    expect($workbook->writeTargets('phone'))->toBe([
        'student.phone',
        'contact.personal_contact',
        'contacts.personal_contact',
    ])->and($workbook->writeTargets('nationality'))->toBe([
        'student.nationality',
        'personal.citizenship',
        'contacts.personal_info.citizenship',
    ]);
});

it('normalizes choice inputs while preserving display values for raw string profile fields', function (): void {
    $workbook = new RegistrarStudentProfileWorkbook;

    // Raw string fields preserve display labels
    expect($workbook->normalizeInput('pwd_type', 'Visual Disability'))->toBe(['Visual Disability', null])
        ->and($workbook->normalizeInput('pwd_type', 'visual'))->toBe(['Visual Disability', null])
        ->and($workbook->normalizeInput('religion', 'Roman Catholic'))->toBe(['Roman Catholic', null])
        ->and($workbook->normalizeInput('religion', 'roman_catholic'))->toBe(['Roman Catholic', null])
        ->and($workbook->normalizeInput('nationality', 'Filipino'))->toBe(['Filipino', null])
        ->and($workbook->normalizeInput('nationality', 'filipino'))->toBe(['Filipino', null])
        ->and($workbook->normalizeInput('emergency_contact_relationship', 'Mother'))->toBe(['Mother', null])
        ->and($workbook->normalizeInput('emergency_contact_relationship', 'mother'))->toBe(['Mother', null])
        ->and($workbook->normalizeInput('guardian_relationship', 'Legal Guardian'))->toBe(['Legal Guardian', null])
        ->and($workbook->normalizeInput('guardian_relationship', 'legal_guardian'))->toBe(['Legal Guardian', null])
        // Coded fields normalize to expected canonical keys
        ->and($workbook->normalizeInput('civil_status', 'Single'))->toBe(['single', null])
        ->and($workbook->normalizeInput('civil_status', 'single'))->toBe(['single', null])
        ->and($workbook->normalizeInput('region_of_origin', 'National Capital Region (NCR)'))->toBe(['NCR', null])
        ->and($workbook->normalizeInput('region_of_origin', 'NCR'))->toBe(['NCR', null])
        ->and($workbook->normalizeInput('region_of_origin', 'ncr'))->toBe(['NCR', null]);
});

it('formats display values case-insensitively for choice fields', function (): void {
    $workbook = new RegistrarStudentProfileWorkbook;

    expect($workbook->displayValue('pwd_type', 'Visual Disability'))->toBe('Visual Disability')
        ->and($workbook->displayValue('pwd_type', 'visual'))->toBe('Visual Disability')
        ->and($workbook->displayValue('civil_status', 'single'))->toBe('Single')
        ->and($workbook->displayValue('civil_status', 'Single'))->toBe('Single')
        ->and($workbook->displayValue('region_of_origin', 'NCR'))->toBe('NCR');
});
