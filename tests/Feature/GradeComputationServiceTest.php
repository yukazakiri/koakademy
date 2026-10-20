<?php

declare(strict_types=1);

use App\Services\GradeComputationService;

/**
 * The grade formula used to be duplicated with hard-coded weights in the API
 * controller, the bulk grade endpoint and the Filament relation manager, so
 * changing the policy in one place silently desynchronised the others.
 */
it('computes the total average from the configured weights', function (): void {
    $service = new GradeComputationService;

    expect($service->totalAverage(80.0, 90.0, 100.0))->toBe(91.0)
        ->and($service->totalAverage(0.0, 0.0, 0.0))->toBe(0.0)
        ->and($service->totalAverage(100.0, 100.0, 100.0))->toBe(100.0);
});

it('rounds the computed average to two decimals', function (): void {
    $service = new GradeComputationService;

    expect($service->totalAverage(81.0, 82.0, 83.0))->toBe(82.1)
        ->and($service->totalAverage(1.0, 1.0, 1.0))->toBe(1.0);
});

it('reads the weights from the configuration', function (): void {
    config()->set('class_grading.weights', [
        'prelim' => 0.5,
        'midterm' => 0.25,
        'finals' => 0.25,
    ]);

    $service = new GradeComputationService;

    expect($service->weights())->toBe([
        'prelim' => 0.5,
        'midterm' => 0.25,
        'finals' => 0.25,
    ])
        ->and($service->totalAverage(100.0, 0.0, 0.0))->toBe(50.0);
});

it('normalises weights that are not expressed as proportions', function (): void {
    config()->set('class_grading.weights', [
        'prelim' => 30,
        'midterm' => 30,
        'finals' => 40,
    ]);

    $service = new GradeComputationService;

    expect($service->totalAverage(100.0, 0.0, 0.0))->toBe(30.0)
        ->and($service->totalAverage(0.0, 0.0, 100.0))->toBe(40.0);
});

it('falls back to the default weights when the configuration is empty', function (): void {
    config()->set('class_grading.weights', []);

    $service = new GradeComputationService;

    expect($service->totalAverage(100.0, 100.0, 0.0))->toBe(60.0);
});

it('returns null when a component is missing', function (): void {
    $service = new GradeComputationService;

    expect($service->totalAverageFor(80.0, 90.0, null))->toBeNull()
        ->and($service->totalAverageFor(null, 90.0, 90.0))->toBeNull()
        ->and($service->totalAverageFor(80.0, 90.0, 90.0))->toBe(87.0);
});
