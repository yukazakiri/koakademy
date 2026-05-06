<?php

declare(strict_types=1);

return [
    'default_mode' => 'annual',
    'modes' => [
        'monthly' => [
            'label' => 'Monthly Income',
            'brackets' => [
                'below_10k' => [
                    'label' => 'Less than {symbol}10,000',
                    'min' => 0,
                    'max' => 9_999,
                ],
                '10k_to_20k' => [
                    'label' => '{symbol}10,000 - {symbol}20,000',
                    'min' => 10_000,
                    'max' => 20_000,
                ],
                '20k_to_40k' => [
                    'label' => '{symbol}20,000 - {symbol}40,000',
                    'min' => 20_000,
                    'max' => 40_000,
                ],
                '40k_to_80k' => [
                    'label' => '{symbol}40,000 - {symbol}80,000',
                    'min' => 40_000,
                    'max' => 80_000,
                ],
                '80k_to_150k' => [
                    'label' => '{symbol}80,000 - {symbol}150,000',
                    'min' => 80_000,
                    'max' => 150_000,
                ],
                '150k_to_250k' => [
                    'label' => '{symbol}150,000 - {symbol}250,000',
                    'min' => 150_000,
                    'max' => 250_000,
                ],
                '250k_to_500k' => [
                    'label' => '{symbol}250,000 - {symbol}500,000',
                    'min' => 250_000,
                    'max' => 500_000,
                ],
                '500k_to_1m' => [
                    'label' => '{symbol}500,000 - {symbol}1,000,000',
                    'min' => 500_000,
                    'max' => 1_000_000,
                ],
                '1m_to_2m' => [
                    'label' => '{symbol}1,000,000 - {symbol}2,000,000',
                    'min' => 1_000_000,
                    'max' => 2_000_000,
                ],
                'above_2m' => [
                    'label' => 'More than {symbol}2,000,000',
                    'min' => 2_000_000,
                    'max' => null,
                ],
            ],
        ],
        'annual' => [
            'label' => 'Annual Income',
            'brackets' => [
                'below_250k' => [
                    'label' => '{symbol}250,000 and below',
                    'min' => 0,
                    'max' => 250_000,
                ],
                '250001_to_400k' => [
                    'label' => '{symbol}250,001 - {symbol}400,000',
                    'min' => 250_001,
                    'max' => 400_000,
                ],
                '400001_to_800k' => [
                    'label' => '{symbol}400,001 - {symbol}800,000',
                    'min' => 400_001,
                    'max' => 800_000,
                ],
                '800001_to_2m' => [
                    'label' => '{symbol}800,001 - {symbol}2,000,000',
                    'min' => 800_001,
                    'max' => 2_000_000,
                ],
                '2m_to_8m' => [
                    'label' => '{symbol}2,000,001 - {symbol}8,000,000',
                    'min' => 2_000_001,
                    'max' => 8_000_000,
                ],
                'above_8m' => [
                    'label' => 'Above {symbol}8,000,000',
                    'min' => 8_000_001,
                    'max' => null,
                ],
            ],
        ],
    ],
];
