<?php

declare(strict_types=1);

namespace App\Services;

final class GradeEvaluationService
{
    /**
     * @param  array<string, mixed>  $policy
     * @return array{numeric_grade: float|null, symbol: string|null, outcome: string, quality_points: float|null, band: array<string, mixed>|null}
     */
    public function evaluate(float|int|string|null $grade, array $policy): array
    {
        if ($grade === null || $grade === '') {
            return [
                'numeric_grade' => null,
                'symbol' => null,
                'outcome' => 'incomplete',
                'quality_points' => null,
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
                'band' => null,
            ];
        }

        $numericGrade = round((float) $grade, (int) ($policy['decimal_places'] ?? 2));
        $zeroIsDropped = (bool) ($policy['zero_is_dropped'] ?? false);
        if ($zeroIsDropped && ($numericGrade === 0.0 || $numericGrade === 0)) {
            return [
                'numeric_grade' => 0.0,
                'symbol' => 'DROPPED',
                'outcome' => 'withdrawn',
                'quality_points' => 0.0,
                'band' => null,
            ];
        }

        // Transferee cross-scale recognition:
        // When the primary policy is percentage-based (e.g. 0-100 or 75-100) and a transferee grade
        // is entered using a decimal point scale (e.g. 1.00–5.00), dynamically detect the point scale,
        // evaluate pass/fail according to point scale cutoff (e.g. <= 3.00), and convert to institutional percentage equivalent.
        $transfereeScaleEnabled = (bool) ($policy['transferee_scale_enabled'] ?? true);
        $policyMax = (float) ($policy['numeric_max'] ?? 100);
        $pointMin = (float) ($policy['transferee_point_scale_min'] ?? 1.0);
        $pointMax = (float) ($policy['transferee_point_scale_max'] ?? 5.0);
        $pointPassing = (float) ($policy['transferee_point_passing_grade'] ?? 3.0);
        $pointDirection = $policy['transferee_point_direction'] ?? 'lower_is_better';
        $conversionMethod = $policy['transferee_conversion_method'] ?? 'formula';

        $isPercentagePolicy = $policyMax >= 50.0;
        $isDecimalTransfereeGrade = $transfereeScaleEnabled
            && $isPercentagePolicy
            && $numericGrade >= $pointMin
            && $numericGrade <= $pointMax;

        if ($isDecimalTransfereeGrade) {
            $isPass = $pointDirection === 'lower_is_better'
                ? $numericGrade <= $pointPassing
                : $numericGrade >= $pointPassing;

            $passBands = $bands->filter(fn (array $b): bool => ($b['outcome'] ?? null) === 'pass' && is_numeric($b['min'] ?? null));
            $instPassing = $passBands->isNotEmpty() ? (float) $passBands->min('min') : 75.0;
            $instMax = $passBands->isNotEmpty() ? (float) $passBands->max('max') : 100.0;

            if ($conversionMethod === 'table') {
                $equivalent = match (true) {
                    $numericGrade <= 1.00 => 99.0,
                    $numericGrade <= 1.25 => 96.0,
                    $numericGrade <= 1.50 => 93.0,
                    $numericGrade <= 1.75 => 90.0,
                    $numericGrade <= 2.00 => 87.0,
                    $numericGrade <= 2.25 => 84.0,
                    $numericGrade <= 2.50 => 81.0,
                    $numericGrade <= 2.75 => 78.0,
                    $numericGrade <= 3.00 => $instPassing,
                    $numericGrade <= 4.00 => max(0.0, $instPassing - 5.0),
                    default => max(0.0, $instPassing - 10.0),
                };
            } else {
                if ($isPass) {
                    $span = max(0.01, $pointPassing - $pointMin);
                    $fraction = ($pointPassing - $numericGrade) / $span;
                    $equivalent = $instPassing + ($fraction * ($instMax - $instPassing));
                } else {
                    $span = max(0.01, $pointMax - $pointPassing);
                    $fraction = ($numericGrade - $pointPassing) / $span;
                    $equivalent = max(0.0, ($instPassing - 1.0) - ($fraction * 15.0));
                }
            }

            $equivalent = round(max(0.0, min($instMax, $equivalent)), 2);

            $targetOutcome = $isPass ? 'pass' : 'fail';
            $matchingBand = $bands->first(fn (array $b): bool => ($b['outcome'] ?? null) === $targetOutcome);

            return [
                'numeric_grade' => $numericGrade,
                'symbol' => number_format($numericGrade, (int) ($policy['decimal_places'] ?? 2)),
                'outcome' => $targetOutcome,
                'quality_points' => $equivalent,
                'band' => $matchingBand,
            ];
        }

        // Reverse cross-scale recognition:
        // When the primary policy is point scale (<= 10.0) and a percentage grade is provided (e.g. 75–100)
        $isPointPolicy = $policyMax <= 10.0;
        $isPercentageTransfereeGrade = $transfereeScaleEnabled
            && $isPointPolicy
            && $numericGrade > 10.0
            && $numericGrade <= 100.0;

        if ($isPercentageTransfereeGrade) {
            $isPass = $numericGrade >= 75.0;
            $span = max(0.01, 100.0 - 75.0);
            $fraction = ($numericGrade - 75.0) / $span;
            $equivalent = $isPass
                ? round($pointPassing - ($fraction * ($pointPassing - $pointMin)), 2)
                : round($pointPassing + (((75.0 - $numericGrade) / 75.0) * ($pointMax - $pointPassing)), 2);

            $targetOutcome = $isPass ? 'pass' : 'fail';
            $matchingBand = $bands->first(fn (array $b): bool => ($b['outcome'] ?? null) === $targetOutcome);

            return [
                'numeric_grade' => $numericGrade,
                'symbol' => number_format($numericGrade, (int) ($policy['decimal_places'] ?? 2)),
                'outcome' => $targetOutcome,
                'quality_points' => $equivalent,
                'band' => $matchingBand,
            ];
        }

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
     * @return array{numeric_grade: float|null, symbol: string|null, outcome: string, quality_points: float|null, band: array<string, mixed>|null}
     */
    private function result(?array $band, ?float $numericGrade, ?string $symbol): array
    {
        return [
            'numeric_grade' => $numericGrade,
            'symbol' => $symbol ?? ($band['symbol'] ?? null),
            'outcome' => $band['outcome'] ?? 'incomplete',
            'quality_points' => is_numeric($band['quality_points'] ?? null) ? (float) $band['quality_points'] : null,
            'band' => $band,
        ];
    }
}
