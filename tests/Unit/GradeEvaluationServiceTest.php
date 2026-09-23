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

it('treats a numeric grade of zero as dropped when zero_is_dropped is enabled', function (): void {
    $policy = [...GradingSystemService::defaults(), 'zero_is_dropped' => true];
    $service = app(GradeEvaluationService::class);

    $result = $service->evaluate(0, $policy);
    expect($result['outcome'])->toBe('withdrawn')
        ->and($result['numeric_grade'])->toBe(0.0)
        ->and($result['symbol'])->toBe('DROPPED');
});

it('treats a numeric grade of zero as failing when zero_is_dropped is disabled', function (): void {
    $policy = [...GradingSystemService::defaults(), 'zero_is_dropped' => false];
    $service = app(GradeEvaluationService::class);

    $result = $service->evaluate(0.0, $policy);
    expect($result['outcome'])->toBe('fail')
        ->and($result['numeric_grade'])->toBe(0.0);
});

it('treats string DRP and DROPPED as dropped', function (): void {
    $policy = GradingSystemService::defaults();
    $service = app(GradeEvaluationService::class);

    expect($service->evaluate('DRP', $policy)['outcome'])->toBe('withdrawn')
        ->and($service->evaluate('DRP', $policy)['symbol'])->toBe('DROPPED')
        ->and($service->evaluate('DROPPED', $policy)['outcome'])->toBe('withdrawn')
        ->and($service->evaluate('DROP', $policy)['outcome'])->toBe('withdrawn');
});

it('treats string W and WITHDRAWN as withdrawn', function (): void {
    $policy = GradingSystemService::defaults();
    $service = app(GradeEvaluationService::class);

    expect($service->evaluate('W', $policy)['outcome'])->toBe('withdrawn')
        ->and($service->evaluate('WITHDRAWN', $policy)['outcome'])->toBe('withdrawn');
});

it('preserves all component scores in calculate even if an earlier required component is missing', function (): void {
    $policy = GradingSystemService::defaults();
    $service = app(GradeEvaluationService::class);

    $scores = [
        'prelim' => null,
        'midterm' => 85.0,
        'final' => 90.0,
    ];

    $result = $service->calculate($scores, $policy);
    expect($result['outcome'])->toBe('incomplete')
        ->and($result['components'])->toMatchArray([
            'prelim' => null,
            'midterm' => 85.0,
            'final' => 90.0,
        ]);
});

it('recognizes transferee decimal point grades in an institutional percentage policy only when transferee context is present', function (): void {
    $policy = [
        ...GradingSystemService::defaults(),
        'numeric_min' => 0,
        'numeric_max' => 100,
        'transferee_scale_enabled' => true,
        'transferee_point_scale_min' => 1.0,
        'transferee_point_scale_max' => 5.0,
        'transferee_point_passing_grade' => 3.0,
        'transferee_point_direction' => 'lower_is_better',
        'transferee_conversion_method' => 'formula',
        'bands' => [
            ['id' => 'pass', 'label' => 'Passing', 'min' => 75, 'max' => 100, 'symbol' => null, 'outcome' => 'pass', 'quality_points' => 4.0, 'color' => 'success', 'sort_order' => 0],
            ['id' => 'fail', 'label' => 'Failing', 'min' => 0, 'max' => 74.9999, 'symbol' => null, 'outcome' => 'fail', 'quality_points' => 0.0, 'color' => 'destructive', 'sort_order' => 1],
        ],
    ];

    $service = app(GradeEvaluationService::class);

    // Without transferee context (e.g. internal class average 2%), it evaluates against primary scale (failing)
    $internalRes = $service->evaluate(2.0, $policy);
    expect($internalRes['outcome'])->toBe('fail')
        ->and($internalRes['quality_points'])->toBe(0.0)
        ->and($internalRes['numeric_grade'])->toBe(2.0);

    // With transferee context: 1.50 in a 1.0-5.0 lower-is-better scale is a high pass
    $res15 = $service->evaluate(1.5, $policy, ['is_transferee' => true]);
    expect($res15['outcome'])->toBe('pass')
        ->and($res15['numeric_grade'])->toBe(1.5)
        ->and($res15['quality_points'])->toBe(4.0) // Matches Passing band's configured quality points (GPA), NOT 93.75
        ->and($res15['equivalent_percentage'])->toBe(93.75);

    // 3.0 is exact passing threshold -> 75%
    $res30 = $service->evaluate(3.0, $policy, ['classification' => 'credited']);
    expect($res30['outcome'])->toBe('pass')
        ->and($res30['quality_points'])->toBe(4.0)
        ->and($res30['equivalent_percentage'])->toBe(75.0);

    // 5.0 is failing
    $res50 = $service->evaluate(5.0, $policy, ['classification' => 'non_credited']);
    expect($res50['outcome'])->toBe('fail')
        ->and($res50['quality_points'])->toBe(0.0)
        ->and($res50['equivalent_percentage'])->toBeLessThan(75.0);
});

it('detects transferee alternate scale before primary-scale rounding', function (): void {
    $policy = [
        ...GradingSystemService::defaults(),
        'numeric_min' => 0,
        'numeric_max' => 100,
        'decimal_places' => 0, // Zero decimal places configured in primary policy
        'transferee_scale_enabled' => true,
        'transferee_point_scale_min' => 1.0,
        'transferee_point_scale_max' => 5.0,
        'transferee_point_passing_grade' => 3.0,
        'transferee_point_direction' => 'lower_is_better',
        'transferee_conversion_method' => 'formula',
    ];

    $service = app(GradeEvaluationService::class);

    // 1.50 should NOT be pre-rounded to 2.0
    $res = $service->evaluate(1.50, $policy, ['is_transferee' => true]);
    expect($res['outcome'])->toBe('pass')
        ->and($res['numeric_grade'])->toBe(1.50)
        ->and($res['equivalent_percentage'])->toBe(93.75);

    // 3.1 should NOT be pre-rounded to 3.0 (which would falsely pass)
    $res31 = $service->evaluate(3.1, $policy, ['is_transferee' => true]);
    expect($res31['outcome'])->toBe('fail')
        ->and($res31['numeric_grade'])->toBe(3.1);
});

it('supports higher-is-better transferee point scales in formula and table modes', function (): void {
    $policy = [
        ...GradingSystemService::defaults(),
        'numeric_min' => 0,
        'numeric_max' => 100,
        'transferee_scale_enabled' => true,
        'transferee_point_scale_min' => 1.0,
        'transferee_point_scale_max' => 5.0,
        'transferee_point_passing_grade' => 3.0,
        'transferee_point_direction' => 'higher_is_better',
        'transferee_conversion_method' => 'formula',
    ];

    $service = app(GradeEvaluationService::class);

    // 5.0 is highest pass -> 100%
    $res5 = $service->evaluate(5.0, $policy, ['is_transferee' => true]);
    expect($res5['outcome'])->toBe('pass')
        ->and($res5['equivalent_percentage'])->toBe(100.0);

    // 3.0 is passing threshold -> 75%
    $res3 = $service->evaluate(3.0, $policy, ['is_transferee' => true]);
    expect($res3['outcome'])->toBe('pass')
        ->and($res3['equivalent_percentage'])->toBe(75.0);

    // 2.0 is failing (< 3.0)
    $res2 = $service->evaluate(2.0, $policy, ['is_transferee' => true]);
    expect($res2['outcome'])->toBe('fail')
        ->and($res2['equivalent_percentage'])->toBeLessThan(75.0);
});

it('supports CHED table conversion for transferee decimal grades', function (): void {
    $policy = [
        ...GradingSystemService::defaults(),
        'numeric_min' => 70,
        'numeric_max' => 100,
        'transferee_scale_enabled' => true,
        'transferee_conversion_method' => 'table',
    ];

    $service = app(GradeEvaluationService::class);

    expect($service->evaluate(1.0, $policy, ['is_transferee' => true])['equivalent_percentage'])->toBe(99.0)
        ->and($service->evaluate(1.25, $policy, ['is_transferee' => true])['equivalent_percentage'])->toBe(96.0)
        ->and($service->evaluate(1.5, $policy, ['is_transferee' => true])['equivalent_percentage'])->toBe(93.0)
        ->and($service->evaluate(2.0, $policy, ['is_transferee' => true])['equivalent_percentage'])->toBe(87.0)
        ->and($service->evaluate(3.0, $policy, ['is_transferee' => true])['equivalent_percentage'])->toBe(75.0);
});
