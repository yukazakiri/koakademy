<?php

declare(strict_types=1);

use App\Enums\StudentType;

it('can verify DHRT student ID prefix and properties', function () {
    // Test that DHRT students use prefix 2
    expect(StudentType::DHRT->getIdPrefix())->toBe('2');

    // Test that DHRT doesn't require LRN
    expect(StudentType::DHRT->requiresLrn())->toBeFalse();

    // Test that DHRT has correct properties
    expect(StudentType::DHRT->getLabel())->toBe('DHRT Student');
    expect(StudentType::DHRT->getAbbreviation())->toBe('DHRT');
    expect(StudentType::DHRT->getDescription())->toBe('DHRT students pursuing specialized technical programs');
});

it('verifies ID prefix requirements for all student types', function () {
    // Test that College, TESDA, and DHRT all use prefix 2
    expect(StudentType::College->getIdPrefix())->toBe('2');
    expect(StudentType::TESDA->getIdPrefix())->toBe('2');
    expect(StudentType::DHRT->getIdPrefix())->toBe('2');

    // Test that SHS uses prefix 3
    expect(StudentType::SeniorHighSchool->getIdPrefix())->toBe('3');
});

it('verifies that only SHS requires LRN', function () {
    // Only SHS should require LRN
    expect(StudentType::SeniorHighSchool->requiresLrn())->toBeTrue();

    // All others should not require LRN
    expect(StudentType::College->requiresLrn())->toBeFalse();
    expect(StudentType::TESDA->requiresLrn())->toBeFalse();
    expect(StudentType::DHRT->requiresLrn())->toBeFalse();
});

it('includes DHRT in student type options', function () {
    $options = StudentType::asSelectOptions();

    expect($options)->toHaveKey('college');
    expect($options)->toHaveKey('shs');
    expect($options)->toHaveKey('tesda');
    expect($options)->toHaveKey('dhrt');

    expect($options['dhrt'])->toBe('DHRT Student');
});
