<?php

declare(strict_types=1);

use App\Services\TuitionPaymentScheduleSettingsService;

it('rounds college prelim and midterm to hundreds and puts the remainder in finals', function (): void {
    $installments = app(TuitionPaymentScheduleSettingsService::class)->installments(14950, 'college');

    expect($installments)->sequence(
        fn ($installment) => $installment->toMatchArray(['term' => 'prelim', 'amount' => 4500.0]),
        fn ($installment) => $installment->toMatchArray(['term' => 'midterm', 'amount' => 4500.0]),
        fn ($installment) => $installment->toMatchArray(['term' => 'finals', 'amount' => 5950.0]),
    );
});

it('preserves centavos in finals and returns no dues for a credit', function (): void {
    $service = app(TuitionPaymentScheduleSettingsService::class);

    expect($service->installments(6793.75, 'college'))->sequence(
        fn ($installment) => $installment->toMatchArray(['amount' => 2000.0]),
        fn ($installment) => $installment->toMatchArray(['amount' => 2000.0]),
        fn ($installment) => $installment->toMatchArray(['amount' => 2793.75]),
    )->and(collect($service->installments(-530, 'college'))->sum('amount'))->toBe(0.0);
});

it('accepts a manual schedule without changing its amounts', function (): void {
    $installments = app(TuitionPaymentScheduleSettingsService::class)->installments(1000, 'college', [
        'prelim' => 250,
        'midterm' => 250,
        'finals' => 500,
    ]);

    expect(collect($installments)->pluck('amount')->all())->toBe([250.0, 250.0, 500.0])
        ->and(collect($installments)->pluck('source')->unique()->all())->toBe(['manual']);
});

it('supports configurable rounded terms and an exact remainder term', function (): void {
    $service = app(TuitionPaymentScheduleSettingsService::class);
    $settings = $service->get();
    $settings['profiles']['college']['rounded_terms'] = ['prelim', 'finals'];
    $settings['profiles']['college']['remainder_term'] = 'midterm';
    $service->update($settings);

    expect($service->installments(14950, 'college'))->sequence(
        fn ($installment) => $installment->toMatchArray(['term' => 'prelim', 'amount' => 4500.0]),
        fn ($installment) => $installment->toMatchArray(['term' => 'midterm', 'amount' => 4450.0]),
        fn ($installment) => $installment->toMatchArray(['term' => 'finals', 'amount' => 6000.0]),
    );
});
