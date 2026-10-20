<?php

declare(strict_types=1);

return [
    'auto_calculate_average' => env('CLASS_GRADING_AUTO_AVERAGE', true),

    /*
    |--------------------------------------------------------------------------
    | Grade Component Weights
    |--------------------------------------------------------------------------
    |
    | The weights applied to the preliminary, midterm and final components when
    | a class enrollment's total average is computed. They are written as
    | proportions and are normalised before use, so both the 0.3/0.3/0.4 and
    | the 30/30/40 notations resolve to the same result.
    |
    | Every code path that computes a total average goes through
    | App\Services\GradeComputationService, so changing a weight here keeps
    | finalised grades and bulk-updated grades in sync.
    |
    */

    'weights' => [
        'prelim' => (float) env('CLASS_GRADING_WEIGHT_PRELIM', 0.3),
        'midterm' => (float) env('CLASS_GRADING_WEIGHT_MIDTERM', 0.3),
        'finals' => (float) env('CLASS_GRADING_WEIGHT_FINALS', 0.4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rounding Precision
    |--------------------------------------------------------------------------
    |
    | Number of decimal places kept on a computed total average.
    |
    */

    'precision' => (int) env('CLASS_GRADING_PRECISION', 2),
];
