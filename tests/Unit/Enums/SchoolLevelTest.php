<?php

declare(strict_types=1);

use App\Enums\SchoolLevel;

it('has expected values', function (SchoolLevel $level, string $expectedValue): void {
    expect($level->value)->toBe($expectedValue);
})->with([
    [SchoolLevel::HigherEducation, 'higher_education'],
    [SchoolLevel::JuniorHigh, 'junior_high'],
    [SchoolLevel::SeniorHigh, 'senior_high'],
    [SchoolLevel::Elementary, 'elementary'],
    [SchoolLevel::TechnicalVocational, 'technical_vocational'],
]);

it('returns select options', function (): void {
    expect(SchoolLevel::asSelectOptions())->toBe([
        'higher_education' => 'College / University',
        'junior_high' => 'Middle School / Junior High School',
        'senior_high' => 'Senior High School',
        'elementary' => 'Elementary / Grade School',
        'technical_vocational' => 'TESDA / Technical-Vocational Institute',
    ]);
});
