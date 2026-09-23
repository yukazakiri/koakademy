<?php

declare(strict_types=1);

use App\Models\Classes;
use App\Models\Student;
use App\Services\ClassEnrollmentService;

it('returns the existing active enrollment for repeated enrollment requests', function (): void {
    $student = Student::factory()->createOne();
    $class = Classes::factory()->createOne();
    $service = app(ClassEnrollmentService::class);

    $first = $service->enrollOnce((int) $student->id, (int) $class->id, ['status' => true]);
    $second = $service->enrollOnce((int) $student->id, (int) $class->id, ['status' => true]);

    expect($second->is($first))->toBeTrue()
        ->and($student->classEnrollments()->where('class_id', $class->id)->count())->toBe(1);
});

it('does not restore or modify a historical soft-deleted enrollment', function (): void {
    $student = Student::factory()->createOne();
    $class = Classes::factory()->createOne();
    $historical = $student->classEnrollments()->create(['class_id' => $class->id, 'status' => false, 'finals_grade' => 88]);
    $historical->delete();

    $current = app(ClassEnrollmentService::class)->enrollOnce((int) $student->id, (int) $class->id, ['status' => true]);

    expect($current->id)->not->toBe($historical->id)
        ->and($historical->fresh()->trashed())->toBeTrue()
        ->and($historical->fresh()->finals_grade)->toBe(88.0);
});

it('validates class enrollment grades against active policy bounds', function (): void {
    $validator = validator(
        ['prelim_grade' => 1000, 'finals_grade' => -5],
        (new App\Http\Requests\ClassEnrollmentFormRequest())->rules()
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('prelim_grade'))->toBeTrue()
        ->and($validator->errors()->has('finals_grade'))->toBeTrue();
});
