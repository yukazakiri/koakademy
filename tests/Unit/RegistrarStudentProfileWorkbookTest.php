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
