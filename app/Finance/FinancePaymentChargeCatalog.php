<?php

declare(strict_types=1);

namespace App\Finance;

final class FinancePaymentChargeCatalog
{
    /** @var array<string, string> */
    private const FEE_LABELS = [
        'registration_fee' => 'Registration Fee',
        'miscelanous_fee' => 'Miscellaneous Fee',
        'diploma_or_certificate' => 'Diploma / Certificate',
        'transcript_of_records' => 'Transcript of Records',
        'certification' => 'Certification',
        'special_exam' => 'Special Exam',
        'id_replacement' => 'ID Replacement',
        'lace_replacement' => 'Lace Replacement',
        'others' => 'Other Fee',
    ];

    /** @return list<string> */
    public static function feeKeys(): array
    {
        return array_keys(self::FEE_LABELS);
    }

    /** @return array<string, string> */
    public static function feeLabels(): array
    {
        return self::FEE_LABELS;
    }

    /** @return list<array{key: string, label: string}> */
    public static function feeOptions(): array
    {
        return array_map(
            static fn (string $key, string $label): array => ['key' => $key, 'label' => $label],
            array_keys(self::FEE_LABELS),
            array_values(self::FEE_LABELS),
        );
    }

    public static function hasFee(string $key): bool
    {
        return array_key_exists($key, self::FEE_LABELS);
    }

    public static function labelFor(string $key): ?string
    {
        return self::FEE_LABELS[$key] ?? null;
    }
}
