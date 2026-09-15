<?php

declare(strict_types=1);

use App\Exports\Sheets\RegistrarEnrollmentDetailSheet;
use App\Models\Student;
use App\Support\RegistrarStudentProfileWorkbook;

it('exposes income ranges and the workbook disability categories through the default student fields', function (): void {
    $workbook = app(RegistrarStudentProfileWorkbook::class);
    foreach (['family_income_bracket', 'father_income_bracket', 'mother_income_bracket'] as $key) {
        expect($workbook->field($key)['type'])->toBe('choice')
            ->and(array_keys($workbook->field($key)['options']))->toBe(array_keys(config('income_brackets.modes.annual.brackets')))
            ->and($workbook->normalizeInput($key, 'below_250k'))->toBe(['below_250k', null])
            ->and($workbook->normalizeInput($key, 'unknown range')[1])->not->toBeNull();
    }
    expect($workbook->field('pwd_type')['options'])->toHaveCount(9)
        ->and($workbook->normalizeInput('pwd_type', 'Non-apparent Rare Disease'))->toBe(['Non-apparent Rare Disease', null])
        ->and($workbook->normalizeInput('is_solo_parent_dependent', 'No'))->toBe([false, null])
        ->and($workbook->field('gender')['options'])->toHaveKey('prefer_not_to_say')
        ->and(collect($workbook->fields())->pluck('key')->all())->toContain(
            'gender',
            'ethnicity',
            'region_of_origin',
            'province_of_origin',
            'city_of_origin',
            'is_indigenous_person',
            'indigenous_group',
            'is_pwd',
            'pwd_type',
            'is_solo_parent',
            'is_solo_parent_dependent',
            'is_senior_citizen',
            'is_magna_carta',
            'is_underprivileged',
            'is_first_generation',
            'family_income_bracket',
        );
});

it('persists the solo parent dependent answer without assuming an unanswered student is not a dependent', function (): void {
    $student = Student::factory()->create();
    expect($student->fresh()->is_solo_parent_dependent)->toBeFalse();
    $student->update(['is_solo_parent_dependent' => false]);
    expect($student->fresh()->is_solo_parent_dependent)->toBeFalse();
    $student->update(['is_solo_parent_dependent' => true]);
    expect($student->fresh()->is_solo_parent_dependent)->toBeTrue();
});

it('exports income labels disability categories and dependent answers while preserving optional blanks', function (): void {
    $workbook = app(RegistrarStudentProfileWorkbook::class);
    $student = Student::factory()->make([
        'is_solo_parent_dependent' => true,
        'pwd_type' => 'Non-apparent Rare Disease',
        'family_income_bracket' => 'below_250k',
        'father_income_bracket' => null,
        'mother_income_bracket' => null,
    ]);
    $student->setRelation('studentParentInfo', null);
    $student->setRelation('studentContactsInfo', null);
    $student->setRelation('studentEducationInfo', null);
    $student->setRelation('personalInfo', null);
    $sheet = new RegistrarEnrollmentDetailSheet([
        ['profile_values' => $workbook->profileValues($student)],
    ], [], $workbook);
    $row = array_combine($sheet->headings(), $sheet->array()[0]);
    expect($row['Dependent of a Solo Parent'])->toBe('Yes')
        ->and($row['Disability Type'])->toBe('Non-apparent Rare Disease')
        ->and($row['Family Income Bracket'])->toBe($workbook->field('family_income_bracket')['options']['below_250k'])
        ->and($row['Father Income Bracket'])->toBe('')
        ->and($row['Mother Income Bracket'])->toBe('')
        ->and($row['Guardian Name'])->toBe('');
});
