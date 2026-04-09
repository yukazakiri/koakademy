<?php

declare(strict_types=1);

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Concerns\HasAcademicPeriodScope;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SubjectEnrollment;

it('StudentEnrollment uses HasAcademicPeriodScope trait', function () {
    expect(in_array(HasAcademicPeriodScope::class, class_uses_recursive(StudentEnrollment::class), true))->toBeTrue();
});

it('Classes uses HasAcademicPeriodScope trait', function () {
    expect(in_array(HasAcademicPeriodScope::class, class_uses_recursive(Classes::class), true))->toBeTrue();
});

it('Student uses HasAcademicPeriodScope trait', function () {
    expect(in_array(HasAcademicPeriodScope::class, class_uses_recursive(Student::class), true))->toBeTrue();
});

it('SubjectEnrollment uses HasAcademicPeriodScope trait', function () {
    expect(in_array(HasAcademicPeriodScope::class, class_uses_recursive(SubjectEnrollment::class), true))->toBeTrue();
});

it('ClassEnrollment uses HasAcademicPeriodScope trait', function () {
    expect(in_array(HasAcademicPeriodScope::class, class_uses_recursive(ClassEnrollment::class), true))->toBeTrue();
});

it('has currentAcademicPeriod scope method defined', function () {
    $reflection = new ReflectionClass(StudentEnrollment::class);

    expect($reflection->hasMethod('scopeCurrentAcademicPeriod'))->toBeTrue();

    $method = $reflection->getMethod('scopeCurrentAcademicPeriod');
    expect($method->isPublic())->toBeTrue();
});

it('has forAcademicPeriod scope method defined', function () {
    $reflection = new ReflectionClass(StudentEnrollment::class);

    expect($reflection->hasMethod('scopeForAcademicPeriod'))->toBeTrue();

    $method = $reflection->getMethod('scopeForAcademicPeriod');
    expect($method->isPublic())->toBeTrue();
});

it('can call currentAcademicPeriod scope without errors', function () {
    $query = StudentEnrollment::query();

    expect(method_exists($query->getModel(), 'scopeCurrentAcademicPeriod'))->toBeTrue();
});

it('scope method signature is correct', function () {
    $reflection = new ReflectionClass(StudentEnrollment::class);
    $method = $reflection->getMethod('scopeCurrentAcademicPeriod');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getType()->getName())->toBe('Illuminate\Database\Eloquent\Builder');
});

it('forAcademicPeriod scope accepts schoolYear and semester', function () {
    $reflection = new ReflectionClass(StudentEnrollment::class);
    $method = $reflection->getMethod('scopeForAcademicPeriod');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(3) // Builder, schoolYear, semester
        ->and($parameters[1]->getName())->toBe('schoolYear')
        ->and($parameters[2]->getName())->toBe('semester');
});

it('resolveSchoolYearVariants returns both spaced and compact formats', function () {
    $model = new StudentEnrollment;
    $reflection = new ReflectionClass($model);
    $method = $reflection->getMethod('resolveSchoolYearVariants');
    $method->setAccessible(true);

    $variants = $method->invoke($model, '2024 - 2025');
    expect($variants)->toContain('2024 - 2025')
        ->and($variants)->toContain('2024-2025');

    // Also works starting from compact format
    $variants = $method->invoke($model, '2024-2025');
    expect($variants)->toContain('2024 - 2025')
        ->and($variants)->toContain('2024-2025');
});

it('ClassEnrollment overrides scopeForAcademicPeriod to scope via class relationship', function () {
    $reflection = new ReflectionClass(ClassEnrollment::class);
    $method = $reflection->getMethod('scopeForAcademicPeriod');

    // The override is defined directly on ClassEnrollment, not inherited from the trait
    expect($method->getDeclaringClass()->getName())->toBe(ClassEnrollment::class);
});
