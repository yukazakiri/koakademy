<?php

declare(strict_types=1);

use App\Models\Student;
use App\Services\DashboardStatisticsService;

/**
 * The grouped aggregate helpers behind the dashboard widgets. They replace the
 * one-COUNT-per-enum-value queries the widgets used to run.
 */
it('counts the rows grouped by a column', function (): void {
    Student::factory()->count(3)->create(['gender' => 'male']);
    Student::factory()->count(2)->create(['gender' => 'female']);

    $counts = app(DashboardStatisticsService::class)->countsByColumn(
        model: Student::class,
        column: 'gender',
    );

    expect($counts['male'])->toBe(3)
        ->and($counts['female'])->toBe(2);
});

it('restricts a grouped count to the given values', function (): void {
    Student::factory()->count(3)->create(['gender' => 'male']);
    Student::factory()->count(2)->create(['gender' => 'female']);

    $counts = app(DashboardStatisticsService::class)->countsByColumn(
        model: Student::class,
        column: 'gender',
        only: ['male'],
    );

    expect($counts)->toBe(['male' => 3]);
});

it('applies the filters of a grouped count', function (): void {
    Student::factory()->count(3)->create(['gender' => 'male', 'academic_year' => 5]);
    Student::factory()->count(2)->create(['gender' => 'male', 'academic_year' => 1]);

    $counts = app(DashboardStatisticsService::class)->countsByColumn(
        model: Student::class,
        column: 'gender',
        filters: [
            ['academic_year', '=', 5],
        ],
    );

    expect($counts)->toBe(['male' => 3]);
});

it('resolves several counters with a single statement', function (): void {
    Student::factory()->count(3)->create(['academic_year' => 5]);
    Student::factory()->count(2)->create(['academic_year' => 1]);

    $counts = app(DashboardStatisticsService::class)->countsByConditions(
        model: Student::class,
        conditions: [
            'graduates' => ['academic_year', '=', 5],
            'first_year' => ['academic_year', '=', 1],
        ],
    );

    expect($counts['graduates'])->toBe(3)
        ->and($counts['first_year'])->toBe(2)
        ->and($counts['total'])->toBe(5);
});

it('resolves a counter for a list of values', function (): void {
    Student::factory()->count(3)->create(['gender' => 'male']);
    Student::factory()->count(2)->create(['gender' => 'female']);

    $counts = app(DashboardStatisticsService::class)->countsByConditions(
        model: Student::class,
        conditions: [
            'known' => ['gender', 'in', ['male', 'female']],
        ],
    );

    expect($counts['known'])->toBe(5)
        ->and($counts['total'])->toBe(5);
});

it('counts the rows per day', function (): void {
    $labels = [
        now()->subDays(2)->format('Y-m-d'),
        now()->subDay()->format('Y-m-d'),
        now()->format('Y-m-d'),
    ];

    Student::factory()->create(['created_at' => now()->subDays(2)]);
    Student::factory()->count(2)->create(['created_at' => now()->subDay()]);
    Student::factory()->create(['created_at' => now()]);

    $counts = app(DashboardStatisticsService::class)->dailyCounts(
        model: Student::class,
        labels: $labels,
    );

    expect($counts[$labels[0]]['total'])->toBe(1)
        ->and($counts[$labels[1]]['total'])->toBe(2)
        ->and($counts[$labels[2]]['total'])->toBe(1);
});

it('counts the rows per day grouped by another column', function (): void {
    $labels = [
        now()->subDay()->format('Y-m-d'),
        now()->format('Y-m-d'),
    ];

    Student::factory()->create(['created_at' => now(), 'gender' => 'male']);
    Student::factory()->create(['created_at' => now(), 'gender' => 'female']);
    Student::factory()->create(['created_at' => now()->subDay(), 'gender' => 'male']);

    $counts = app(DashboardStatisticsService::class)->dailyCounts(
        model: Student::class,
        labels: $labels,
        column: 'gender',
    );

    expect($counts[$labels[0]]['male'])->toBe(1)
        ->and($counts[$labels[1]]['male'])->toBe(1)
        ->and($counts[$labels[1]]['female'])->toBe(1);
});

it('counts the rows per period', function (): void {
    $periods = [
        [
            'start' => now()->subDays(2)->startOfDay(),
            'end' => now()->subDays(2)->endOfDay(),
        ],
        [
            'start' => now()->subDay()->startOfDay(),
            'end' => now()->subDay()->endOfDay(),
        ],
    ];

    Student::factory()->create(['created_at' => now()->subDays(2)]);
    Student::factory()->count(2)->create(['created_at' => now()->subDay()]);

    $counts = app(DashboardStatisticsService::class)->countsByPeriods(
        model: Student::class,
        periods: $periods,
        table: 'students',
        timestampColumn: 'created_at',
    );

    expect($counts[0]['total'])->toBe(1)
        ->and($counts[1]['total'])->toBe(2);
});

it('counts the rows per period grouped by another column', function (): void {
    $periods = [
        [
            'start' => now()->subDay()->startOfDay(),
            'end' => now()->subDay()->endOfDay(),
        ],
    ];

    Student::factory()->create(['created_at' => now()->subDay(), 'gender' => 'male']);
    Student::factory()->create(['created_at' => now()->subDay(), 'gender' => 'female']);

    $counts = app(DashboardStatisticsService::class)->countsByPeriods(
        model: Student::class,
        periods: $periods,
        table: 'students',
        timestampColumn: 'created_at',
        column: 'gender',
    );

    expect($counts[0]['male'])->toBe(1)
        ->and($counts[0]['female'])->toBe(1);
});
