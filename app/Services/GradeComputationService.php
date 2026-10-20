<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Single source of truth for the class enrollment grade formula.
 *
 * The component weights live in `config/class_grading.php` so the grading
 * policy can be changed in exactly one place. Every code path that computes a
 * total average (finalising a single grade, bulk grade updates, the Filament
 * relation manager preview) resolves it through this service, which keeps
 * finalised grades and bulk-updated grades from drifting apart.
 */
final class GradeComputationService
{
    public const string PRELIM = 'prelim';

    public const string MIDTERM = 'midterm';

    public const string FINALS = 'finals';

    /**
     * @var array<string, float>|null
     */
    private ?array $resolvedWeights = null;

    /**
     * The component weights, normalised so they always sum to 1.
     *
     * Weights are resolved from configuration and expressed as proportions.
     * A configuration that does not sum to 1 (for example 30/30/40) is scaled
     * proportionally, and a configuration that sums to 0 falls back to the
     * built-in defaults.
     *
     * @return array<string, float>
     */
    public function weights(): array
    {
        if ($this->resolvedWeights !== null) {
            return $this->resolvedWeights;
        }

        $configured = config('class_grading.weights', []);
        $configured = is_array($configured) ? $configured : [];

        $weights = [];
        $total = 0.0;

        foreach ([self::PRELIM, self::MIDTERM, self::FINALS] as $component) {
            $value = $configured[$component] ?? null;
            $value = is_numeric($value) ? max(0.0, (float) $value) : 0.0;

            $weights[$component] = $value;
            $total += $value;
        }

        if ($total <= 0.0) {
            $weights = self::defaultWeights();
            $total = array_sum($weights);
        }

        foreach ($weights as $component => $value) {
            $weights[$component] = round($value / $total, 6);
        }

        return $this->resolvedWeights = $weights;
    }

    /**
     * Compute the total average from the three graded components.
     */
    public function totalAverage(float $prelim, float $midterm, float $finals): float
    {
        $weights = $this->weights();

        $total = ($prelim * $weights[self::PRELIM])
            + ($midterm * $weights[self::MIDTERM])
            + ($finals * $weights[self::FINALS]);

        return round($total, $this->precision());
    }

    /**
     * Compute the total average, or null when a component has no score yet.
     */
    public function totalAverageFor(float|int|string|null $prelim, float|int|string|null $midterm, float|int|string|null $finals): ?float
    {
        if (! is_numeric($prelim) || ! is_numeric($midterm) || ! is_numeric($finals)) {
            return null;
        }

        return $this->totalAverage((float) $prelim, (float) $midterm, (float) $finals);
    }

    /**
     * Number of decimal places kept on a computed total average.
     */
    public function precision(): int
    {
        $precision = config('class_grading.precision', 2);

        return max(0, min(6, is_numeric($precision) ? (int) $precision : 2));
    }

    /**
     * @return array<string, float>
     */
    private static function defaultWeights(): array
    {
        return [
            self::PRELIM => 0.3,
            self::MIDTERM => 0.3,
            self::FINALS => 0.4,
        ];
    }
}
