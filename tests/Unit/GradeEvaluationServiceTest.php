<?php

declare(strict_types=1);

use App\Services\GradeEvaluationService;
use App\Services\GradingSystemService;

it('calculates the default prelim midterm final weighting', function (): void {
    $policy = GradingSystemService::defaults();
    $result = app(GradeEvaluationService::class)->calculate([
        'prelim' => 80,
        'midterm' => 90,
        'final' => 100,
    ], $policy);

    expect($result['numeric_grade'])->toBe(91.0)
        ->and($result['outcome'])->toBe('pass')
        ->and($result['components'])->toBe([
            'prelim' => 80.0,
            'midterm' => 90.0,
            'final' => 100.0,
        ]);
});

it('evaluates a lower-is-better international numeric scale', function (): void {
    $policy = [
        ...GradingSystemService::defaults(),
        'numeric_min' => 1,
        'numeric_max' => 5,
        'direction' => 'lower_is_better',
        'bands' => [
            ['id' => 'pass', 'label' => 'Pass', 'min' => 1, 'max' => 3, 'symbol' => null, 'outcome' => 'pass', 'quality_points' => 2, 'color' => 'success', 'sort_order' => 0],
            ['id' => 'fail', 'label' => 'Fail', 'min' => 3.0001, 'max' => 5, 'symbol' => null, 'outcome' => 'fail', 'quality_points' => 0, 'color' => 'destructive', 'sort_order' => 1],
        ],
    ];

    $service = app(GradeEvaluationService::class);

    expect($service->evaluate(2.5, $policy)['outcome'])->toBe('pass')
        ->and($service->evaluate(4.0, $policy)['outcome'])->toBe('fail');
});

it('evaluates symbolic grades without a country-specific numeric threshold', function (): void {
    $policy = [
        ...GradingSystemService::defaults(),
        'input_type' => 'symbol',
        'bands' => [
            ['id' => 'distinction', 'label' => 'Distinction', 'min' => null, 'max' => null, 'symbol' => 'D', 'outcome' => 'pass', 'quality_points' => 4, 'color' => 'success', 'sort_order' => 0],
            ['id' => 'refer', 'label' => 'Refer', 'min' => null, 'max' => null, 'symbol' => 'R', 'outcome' => 'fail', 'quality_points' => 0, 'color' => 'destructive', 'sort_order' => 1],
        ],
    ];

    $service = app(GradeEvaluationService::class);

    expect($service->evaluate('d', $policy))->toMatchArray([
        'symbol' => 'D',
        'outcome' => 'pass',
        'quality_points' => 4.0,
    ])
        ->and($service->evaluate('R', $policy)['outcome'])->toBe('fail');
});
