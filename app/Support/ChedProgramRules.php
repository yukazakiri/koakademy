<?php

declare(strict_types=1);

namespace App\Support;

final class ChedProgramRules
{
    public const array REPORTING_LEVELS = [
        'doctoral' => 'Doctoral',
        'masters' => 'Masters',
        'post_baccalaureate' => 'Post-Baccalaureate',
        'baccalaureate' => 'Baccalaureate',
        'pre_baccalaureate' => 'Pre-Baccalaureate',
        'voctech' => 'VocTech',
        'basic' => 'Basic',
    ];

    /** @return array<string, array<int, mixed>> */
    public static function validationRules(): array
    {
        return [
            'ched_reporting_level' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::REPORTING_LEVELS))],
            'ched_program_code' => ['nullable', 'string', 'max:50'],
            'ched_major_code' => ['nullable', 'string', 'max:50'],
            'ched_other_delivery_mode' => ['nullable', 'string', 'max:255'],
            'ched_major' => ['nullable', 'string', 'max:255'],
            'ched_major_code' => ['nullable', 'string', 'max:50'],
            'ched_has_thesis' => ['nullable', 'boolean'],
            'ched_program_status' => ['nullable', 'string', 'in:CO,PO,DO,NO,NA'],
            'ched_year_implemented' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ched_authority_category' => ['nullable', 'string', 'in:GP,GR,BR,OT'],
            'ched_authority_serial' => ['nullable', 'string', 'max:100'],
            'ched_authority_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ched_authority_other_program' => ['nullable', 'string', 'max:255'],
            'ched_delivery_mode' => ['nullable', 'string', 'in:SE,TR,SD,TD,DE,OT'],
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
            'reporting_levels' => self::labelled(self::REPORTING_LEVELS),
            'has_thesis' => self::labelled(['1' => 'Yes', '0' => 'No']),
            'program_statuses' => self::labelled(['CO' => 'CO', 'PO' => 'PO', 'DO' => 'DO', 'NO' => 'NO', 'NA' => 'NA']),
            'authority_categories' => self::labelled(['GP' => 'GP', 'GR' => 'GR', 'BR' => 'BR', 'OT' => 'OT']),
            'delivery_modes' => self::labelled(['SE' => 'SE', 'TR' => 'TR', 'SD' => 'SD', 'TD' => 'TD', 'DE' => 'DE', 'OT' => 'OT — Other (specify)']),
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
