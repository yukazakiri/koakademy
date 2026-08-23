<?php

declare(strict_types=1);

namespace App\Support;

final class ChedProgramRules
{
    /** @return array<string, array<int, mixed>> */
    public static function validationRules(): array
    {
        return [
            'ched_major' => ['nullable', 'string', 'max:255'],
            'ched_has_thesis' => ['nullable', 'boolean'],
            'ched_program_status' => ['nullable', 'string', 'in:CO,PO,DO,NO,NA'],
            'ched_authority_category' => ['nullable', 'string', 'in:GP,GR,BR,OT'],
            'ched_authority_serial' => ['nullable', 'string', 'max:100'],
            'ched_authority_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ched_authority_other_program' => ['nullable', 'string', 'max:255'],
            'ched_delivery_mode' => ['nullable', 'string', 'in:SE,TR,SD,TD,DE'],
            'ched_normal_length_years' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'ched_program_credit_units' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'ched_tuition_per_unit' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'ched_program_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    public static function options(): array
    {
        return [
            'has_thesis' => self::labelled(['1' => 'Yes', '0' => 'No']),
            'program_statuses' => self::labelled(['CO' => 'CO', 'PO' => 'PO', 'DO' => 'DO', 'NO' => 'NO', 'NA' => 'NA']),
            'authority_categories' => self::labelled(['GP' => 'GP', 'GR' => 'GR', 'BR' => 'BR', 'OT' => 'OT']),
            'delivery_modes' => self::labelled(['SE' => 'SE', 'TR' => 'TR', 'SD' => 'SD', 'TD' => 'TD', 'DE' => 'DE']),
        ];
    }

    /** @param array<string, string> $options
     *  @return list<array{value: string, label: string}> */
    private static function labelled(array $options): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys($options),
            array_values($options),
        );
    }
}
