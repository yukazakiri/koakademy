<?php

declare(strict_types=1);

namespace App\Services;

final class GradeEvaluationService
{
    /**
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>|bool  $context
     * @return array{numeric_grade: float|null, symbol: string|null, outcome: string, quality_points: float|null, equivalent_percentage: float|null, band: array<string, mixed>|null}
     */
    public function evaluate(float|int|string|null $grade, array $policy, array|bool $context = []): array
    {
        if ($grade === null || $grade === '') {
            return [
                'numeric_grade' => null,
                'symbol' => null,
                'outcome' => 'incomplete',
                'quality_points' => null,
                'equivalent_percentage' => null,
                'band' => null,
            ];
        }

        $symbolic = mb_strtoupper(mb_trim((string) $grade));
        if (in_array($symbolic, ['DROP', 'DROPPED', 'DRP', 'W', 'WITHDRAWN', 'INC', 'INCOMPLETE'], true)) {
            $outcome = in_array($symbolic, ['DROP', 'DROPPED', 'DRP'], true)
                ? 'withdrawn'
                : (in_array($symbolic, ['W', 'WITHDRAWN'], true) ? 'withdrawn' : 'incomplete');

            return [
                'numeric_grade' => is_numeric($grade) ? (float) $grade : 0.0,
                'symbol' => in_array($symbolic, ['DROP', 'DROPPED', 'DRP'], true) ? 'DROPPED' : $symbolic,
                'outcome' => $outcome,
                'quality_points' => 0.0,
                'equivalent_percentage' => 0.0,
                'band' => null,
            ];
        }

        $inputType = $policy['input_type'] ?? 'numeric';
        $bands = collect($policy['bands'] ?? []);

        if ($inputType === 'symbol') {
            $symbol = mb_strtoupper(mb_trim((string) $grade));
            $band = $bands->first(fn (array $candidate): bool => mb_strtoupper((string) ($candidate['symbol'] ?? '')) === $symbol);

            return $this->result($band, null, $symbol);
        }

        if (! is_numeric($grade)) {
            return [
                'numeric_grade' => null,
                'symbol' => null,
                'outcome' => 'incomplete',
                'quality_points' => null,
                'equivalent_percentage' => null,
                'band' => null,
            ];
        }

        $rawNumeric = (float) $grade;

        $zeroIsDropped = (bool) ($policy['zero_is_dropped'] ?? false);
        if ($zeroIsDropped && ($rawNumeric === 0.0 || $rawNumeric === 0)) {
            return [
                'numeric_grade' => 0.0,
                'symbol' => 'DROPPED',
                'outcome' => 'withdrawn',
                'quality_points' => 0.0,
                'equivalent_percentage' => 0.0,
                'band' => null,
            ];
        }

        // Transferee cross-scale recognition:
        // Only consider transferee alternate scale when explicitly flagged as transferee/credited context.
        // This prevents internal student averages (e.g. 2% or 3%) from being misclassified as passing point-scale grades.
        $isTransferee = is_bool($context) ? $context : (bool) (
            ($context['is_transferee'] ?? false) ||
            in_array($context['classification'] ?? null, ['credited', 'non_credited'], true)
        );

        $transfereeScaleEnabled = (bool) ($policy['transferee_scale_enabled'] ?? true);
        $policyMax = (float) ($policy['numeric_max'] ?? 100);
        $pointMin = (float) ($policy['transferee_point_scale_min'] ?? 1.0);
        $pointMax = (float) ($policy['transferee_point_scale_max'] ?? 5.0);
        $pointPassing = (float) ($policy['transferee_point_passing_grade'] ?? 3.0);
        $pointDirection = $policy['transferee_point_direction'] ?? 'lower_is_better';
        $conversionMethod = $policy['transferee_conversion_method'] ?? 'formula';

        $isPercentagePolicy = $policyMax >= 50.0;
        // Detect alternate scale using $rawNumeric BEFORE primary scale decimal rounding
        $isDecimalTransfereeGrade = $isTransferee
            && $transfereeScaleEnabled
            && $isPercentagePolicy
            && $rawNumeric >= $pointMin
            && $rawNumeric <= $pointMax;

        if ($isDecimalTransfereeGrade) {
            $isPass = $pointDirection === 'lower_is_better'
                ? $rawNumeric <= $pointPassing
                : $rawNumeric >= $pointPassing;

            $passBands = $bands->filter(fn (array $b): bool => ($b['outcome'] ?? null) === 'pass' && is_numeric($b['min'] ?? null));
            $instPassing = $passBands->isNotEmpty() ? (float) $passBands->min('min') : 75.0;
            $instMax = $passBands->isNotEmpty() ? (float) $passBands->max('max') : 100.0;

            if ($conversionMethod === 'table') {
                if ($pointDirection === 'higher_is_better') {
                    $equivalent = match (true) {
                        $rawNumeric >= 5.00 => 99.0,
                        $rawNumeric >= 4.75 => 96.0,
                        $rawNumeric >= 4.50 => 93.0,
                        $rawNumeric >= 4.25 => 90.0,
                        $rawNumeric >= 4.00 => 87.0,
                        $rawNumeric >= 3.75 => 84.0,
                        $rawNumeric >= 3.50 => 81.0,
                        $rawNumeric >= 3.25 => 78.0,
                        $rawNumeric >= 3.00 => $instPassing,
                        $rawNumeric >= 2.00 => max(0.0, $instPassing - 5.0),
                        default => max(0.0, $instPassing - 10.0),
                    };
                } else {
                    $equivalent = match (true) {
                        $rawNumeric <= 1.00 => 99.0,
                        $rawNumeric <= 1.25 => 96.0,
                        $rawNumeric <= 1.50 => 93.0,
                        $rawNumeric <= 1.75 => 90.0,
                        $rawNumeric <= 2.00 => 87.0,
                        $rawNumeric <= 2.25 => 84.0,
                        $rawNumeric <= 2.50 => 81.0,
                        $rawNumeric <= 2.75 => 78.0,
                        $rawNumeric <= 3.00 => $instPassing,
                        $rawNumeric <= 4.00 => max(0.0, $instPassing - 5.0),
                        default => max(0.0, $instPassing - 10.0),
                    };
                }
            } else {
                if ($pointDirection === 'higher_is_better') {
                    if ($isPass) {
                        $span = max(0.01, $pointMax - $pointPassing);
                        $fraction = ($rawNumeric - $pointPassing) / $span;
                        $equivalent = $instPassing + ($fraction * ($instMax - $instPassing));
                    } else {
                        $span = max(0.01, $pointPassing - $pointMin);
                        $fraction = ($pointPassing - $rawNumeric) / $span;
                        $equivalent = max(0.0, ($instPassing - 1.0) - ($fraction * 15.0));
                    }
                } else {
                    if ($isPass) {
                        $span = max(0.01, $pointPassing - $pointMin);
                        $fraction = ($pointPassing - $rawNumeric) / $span;
                        $equivalent = $instPassing + ($fraction * ($instMax - $instPassing));
                    } else {
                        $span = max(0.01, $pointMax - $pointPassing);
                        $fraction = ($rawNumeric - $pointPassing) / $span;
                        $equivalent = max(0.0, ($instPassing - 1.0) - ($fraction * 15.0));
                    }
                }
            }

            $equivalent = round(max(0.0, min($instMax, $equivalent)), 2);

            $targetOutcome = $isPass ? 'pass' : 'fail';
            // Translate equivalent percentage into the target band to preserve configured quality points
            $matchingBand = $bands->first(function (array $b) use ($equivalent): bool {
                if (! is_numeric($b['min'] ?? null) || ! is_numeric($b['max'] ?? null)) {
                    return false;
                }

                return $equivalent >= (float) $b['min'] && $equivalent <= (float) $b['max'];
            }) ?? $bands->first(fn (array $b): bool => ($b['outcome'] ?? null) === $targetOutcome);

            $qualityPoints = null;
            if ($matchingBand && is_numeric($matchingBand['quality_points'] ?? null)) {
                $qualityPoints = (float) $matchingBand['quality_points'];
            }

            return [
                'numeric_grade' => $rawNumeric,
                'symbol' => number_format($rawNumeric, 2),
                'outcome' => $targetOutcome,
                'quality_points' => $qualityPoints,
                'equivalent_percentage' => $equivalent,
                'band' => $matchingBand,
            ];
        }

        // Reverse cross-scale recognition:
        // When the primary policy is point scale (<= 10.0) and a percentage grade is provided (e.g. 75–100)
        $isPointPolicy = $policyMax <= 10.0;
        $isPercentageTransfereeGrade = $isTransferee
            && $transfereeScaleEnabled
            && $isPointPolicy
            && $rawNumeric > 10.0
            && $rawNumeric <= 100.0;

        if ($isPercentageTransfereeGrade) {
            $isPass = $rawNumeric >= 75.0;
            $span = max(0.01, 100.0 - 75.0);
            $fraction = ($rawNumeric - 75.0) / $span;
            $equivalent = $isPass
                ? round($pointPassing - ($fraction * ($pointPassing - $pointMin)), 2)
                : round($pointPassing + (((75.0 - $rawNumeric) / 75.0) * ($pointMax - $pointPassing)), 2);

            $targetOutcome = $isPass ? 'pass' : 'fail';
            $matchingBand = $bands->first(function (array $b) use ($equivalent): bool {
                if (! is_numeric($b['min'] ?? null) || ! is_numeric($b['max'] ?? null)) {
                    return false;
                }

                return $equivalent >= (float) $b['min'] && $equivalent <= (float) $b['max'];
            }) ?? $bands->first(fn (array $b): bool => ($b['outcome'] ?? null) === $targetOutcome);

            $qualityPoints = null;
            if ($matchingBand && is_numeric($matchingBand['quality_points'] ?? null)) {
                $qualityPoints = (float) $matchingBand['quality_points'];
            }

            return [
                'numeric_grade' => $rawNumeric,
                'symbol' => number_format($rawNumeric, 2),
                'outcome' => $targetOutcome,
                'quality_points' => $qualityPoints,
                'equivalent_percentage' => $rawNumeric,
                'band' => $matchingBand,
            ];
        }

        $numericGrade = round($rawNumeric, (int) ($policy['decimal_places'] ?? 2));

        $band = $bands->first(function (array $candidate) use ($numericGrade): bool {
            if (! is_numeric($candidate['min'] ?? null) || ! is_numeric($candidate['max'] ?? null)) {
                return false;
            }

            return $numericGrade >= (float) $candidate['min'] && $numericGrade <= (float) $candidate['max'];
        });

        return $this->result($band, $numericGrade, null);
    }

    /**
     * @param  array<string, mixed>  $policy
     * @param  array<string, float|int|string|null>  $componentScores
     * @return array{numeric_grade: float|null, symbol: string|null, outcome: string, quality_points: float|null, band: array<string, mixed>|null, components: array<string, float|null>}
     */
    public function calculate(array $componentScores, array $policy): array
    {
        $normalized = [];
        $weightedTotal = 0.0;
        $hasMissingRequired = false;

        foreach ($policy['components'] ?? [] as $component) {
            $key = (string) ($component['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $raw = $componentScores[$key] ?? null;
            $score = is_numeric($raw) ? (float) $raw : null;
            $normalized[$key] = $score;

            if ($score === null && ($component['required'] ?? true)) {
                $hasMissingRequired = true;
            }

            if ($score !== null) {
                $weightedTotal += $score * ((float) ($component['weight'] ?? 0) / 100);
            }
        }

        if ($hasMissingRequired) {
            return [...$this->evaluate(null, $policy), 'components' => $normalized];
        }

        return [...$this->evaluate($weightedTotal, $policy), 'components' => $normalized];
    }

    /**
     * @param  array<string, mixed>|null  $band
     * @return array{numeric_grade: float|null, symbol: string|null, outcome: string, quality_points: float|null, equivalent_percentage: float|null, band: array<string, mixed>|null}
     */
    private function result(?array $band, ?float $numericGrade, ?string $symbol, ?float $equivalentPercentage = null): array
    {
        return [
            'numeric_grade' => $numericGrade,
            'symbol' => $symbol ?? ($band['symbol'] ?? null),
            'outcome' => $band['outcome'] ?? 'incomplete',
            'quality_points' => is_numeric($band['quality_points'] ?? null) ? (float) $band['quality_points'] : null,
            'equivalent_percentage' => $equivalentPercentage ?? $numericGrade,
            'band' => $band,
        ];
    }
}
