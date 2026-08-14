<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentType;
use App\Models\GeneralSetting;

final readonly class TuitionPaymentScheduleSettingsService
{
    private const string CONFIG_KEY = 'tuition_payment_schedule';

    /** @return array{profiles: array<string, array<string, mixed>>} */
    public function get(): array
    {
        $configured = GeneralSetting::query()->first()?->more_configs[self::CONFIG_KEY] ?? [];
        $profiles = [];

        foreach (StudentType::cases() as $studentType) {
            $profiles[$studentType->value] = array_replace(
                $this->defaultProfile($studentType),
                is_array($configured['profiles'][$studentType->value] ?? null)
                    ? $configured['profiles'][$studentType->value]
                    : [],
            );
        }

        return ['profiles' => $profiles];
    }

    /** @param array{profiles: array<string, array<string, mixed>>} $configuration */
    public function update(array $configuration): void
    {
        $settings = GeneralSetting::query()->firstOrCreate([], ['site_name' => config('app.name')]);
        $moreConfigs = is_array($settings->more_configs) ? $settings->more_configs : [];
        $moreConfigs[self::CONFIG_KEY] = $configuration;
        $settings->update(['more_configs' => $moreConfigs]);
    }

    /** @return array<string, mixed> */
    public function profile(string $studentType): array
    {
        return $this->get()['profiles'][$studentType] ?? $this->defaultProfile(StudentType::College);
    }

    /**
     * @param  array<string, float|int>|null  $overrides
     * @return list<array{term: string, sequence: int, percentage: float, amount: float, source: string}>
     */
    public function installments(float $balance, string $studentType, ?array $overrides = null): array
    {
        $terms = ['prelim', 'midterm', 'finals'];
        $profile = $this->profile($studentType);
        $balance = round(max(0, $balance), 2);
        $percentages = $profile['percentages'];
        $remainderTerm = in_array($profile['remainder_term'] ?? null, $terms, true)
            ? (string) $profile['remainder_term']
            : 'finals';

        if ($overrides !== null) {
            $amounts = [
                'prelim' => round((float) ($overrides['prelim'] ?? 0), 2),
                'midterm' => round((float) ($overrides['midterm'] ?? 0), 2),
                'finals' => round((float) ($overrides['finals'] ?? 0), 2),
            ];
            $source = 'manual';
        } elseif (! (bool) $profile['enabled'] || $balance <= 0) {
            $amounts = array_fill_keys($terms, 0.0);
            $amounts[$remainderTerm] = $balance;
            $source = 'generated';
        } else {
            $increment = max(0.01, (float) $profile['rounding_increment']);
            $mode = (string) $profile['rounding_mode'];
            $roundedTerms = collect($profile['rounded_terms'] ?? ['prelim', 'midterm']);
            $amounts = array_fill_keys($terms, 0.0);
            $allocated = 0.0;

            foreach ($terms as $term) {
                if ($term === $remainderTerm) {
                    continue;
                }

                $raw = $balance * (float) $percentages[$term] / 100;
                $amount = $roundedTerms->contains($term)
                    ? $this->roundedAmount($raw, $increment, $mode)
                    : round($raw, 2);
                $amounts[$term] = round(min(max(0, $amount), max(0, $balance - $allocated)), 2);
                $allocated = round($allocated + $amounts[$term], 2);
            }

            $amounts[$remainderTerm] = round($balance - $allocated, 2);
            $source = 'generated';
        }

        return collect($terms)
            ->values()
            ->map(fn (string $term, int $index): array => [
                'term' => $term,
                'sequence' => $index + 1,
                'percentage' => (float) $percentages[$term],
                'amount' => $amounts[$term],
                'source' => $source,
            ])
            ->all();
    }

    private function roundedAmount(float $amount, float $increment, string $mode): float
    {
        $value = $amount / $increment;
        $rounded = match ($mode) {
            'down' => floor($value),
            'up' => ceil($value),
            default => round($value, 0, PHP_ROUND_HALF_UP),
        };

        return $rounded * $increment;
    }

    /** @return array<string, mixed> */
    private function defaultProfile(StudentType $studentType): array
    {
        return [
            'enabled' => $studentType === StudentType::College,
            'percentages' => ['prelim' => 30, 'midterm' => 30, 'finals' => 40],
            'rounding_increment' => 100,
            'rounding_mode' => 'nearest',
            'rounded_terms' => ['prelim', 'midterm'],
            'remainder_term' => 'finals',
        ];
    }
}
