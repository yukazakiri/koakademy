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

        foreach ($policy['components'] ?? [] as $component) {
            $key = (string) ($component['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $raw = $componentScores[$key] ?? null;
            $score = is_numeric($raw) ? (float) $raw : null;
            $normalized[$key] = $score;

            if ($score === null && ($component['required'] ?? true)) {
                return [...$this->evaluate(null, $policy), 'components' => $normalized];
            }

            if ($score !== null) {
                $weightedTotal += $score * ((float) ($component['weight'] ?? 0) / 100);
            }
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
