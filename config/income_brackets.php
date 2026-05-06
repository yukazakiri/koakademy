<?php

declare(strict_types=1);

return [
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
];
