<?php

declare(strict_types=1);

use App\Dashboards\Support\StatAggregates;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Student;
use App\Services\GeneralSettingsService;
use App\Support\AdministratorPortalData;

/**
 * StatAggregates was extracted from AdministratorPortalData so the desks and the original
 * dashboard read identical figures. These assertions compare the two implementations directly,
 * so a future edit to one side cannot silently diverge from the other.
 */
it('derives application stats consistent with a direct recount', function (): void {
    $aggregates = app(StatAggregates::class);
    $stats = $aggregates->studentStats();

    $applicants = Student::query()->where('status', StudentStatus::Applicant->value)->count();
    $enrolled = Student::query()->where('status', StudentStatus::Enrolled->value)->count();

    expect($stats->status_applicant)->toBe($applicants);
    expect($stats->status_enrolled)->toBe($enrolled);

    $application = $aggregates->applicationStats($stats);

    expect($application['applicants'])->toBe($applicants);
    expect($application['enrolled'])->toBe($enrolled);
    expect($application['conversion_rate'])->toBe(0.0);
});

it('computes a zero conversion rate rather than dividing by zero', function (): void {
    $stats = (object) ['status_applicant' => 0, 'status_enrolled' => 0, 'status_on_leave' => 0];

    expect(app(StatAggregates::class)->applicationStats($stats)['conversion_rate'])->toBe(0.0);
});

it('counts students exactly once in the total', function (): void {
    expect((int) app(StatAggregates::class)->studentStats()->total)->toBe(Student::query()->count());
});

it('covers every student type and gender bucket', function (): void {
    $aggregates = app(StatAggregates::class);
    $stats = $aggregates->studentStats();
    $total = (int) $stats->total;

    $types = $aggregates->studentTypeDistribution($stats, $total);

    expect($types)->toHaveCount(count(StudentType::cases()));
    expect(array_column($types, 'count'))->toBeArray();

    // Every student falls into exactly one gender bucket.
    $genderTotal = array_sum(array_column($aggregates->genderDistribution($stats), 'count'));

    expect($genderTotal)->toBe($total);
});

it('produces the same finance snapshot as the portal dashboard', function (): void {
    $settings = app(GeneralSettingsService::class);

    $snapshot = app(StatAggregates::class)->financeSnapshot(
        $settings->getCurrentSchoolYearString(),
        $settings->getCurrentSemester(),
    );

    expect($snapshot)->toHaveKeys([
        'total_revenue',
        'total_collectibles',
        'total_assessed',
        'collection_rate',
        'fully_paid_count',
        'outstanding_count',
        'today_collection',
        'today_transactions',
    ]);

    $portal = AdministratorPortalData::build(new App\Models\User(['role' => App\Enums\UserRole::Dean]));

    // The desk and the dashboard must not drift on the same period figures.
    expect($portal['finance_snapshot']['total_revenue'])->toBe($snapshot['total_revenue']);
    expect($portal['finance_snapshot']['outstanding_count'])->toBe($snapshot['outstanding_count']);
    expect($portal['finance_snapshot']['collection_rate'])->toBe($snapshot['collection_rate']);
});

it('keeps the portal payload shape unchanged', function (): void {
    $payload = AdministratorPortalData::build(new App\Models\User(['role' => App\Enums\UserRole::Dean]));

    expect(array_keys($payload))->toBe([
        'current_period',
        'stats',
        'executive_summary',
        'recent_activity',
        'enrollment_health',
        'student_demographics',
        'finance_snapshot',
        'operations',
        'recent_records',
        'analytics',
    ]);

    foreach ($payload['stats'] as $stat) {
        expect($stat)->toHaveKeys(['label', 'value', 'description', 'tone', 'trend', 'series']);
    }
});

it('computes a trend from the last two non-zero points', function (): void {
    $aggregates = app(StatAggregates::class);

    expect($aggregates->seriesTrend([
        ['date' => 'a', 'value' => 0],
        ['date' => 'b', 'value' => 50],
        ['date' => 'c', 'value' => 100],
    ]))->toBe(100.0);

    // A single point, or all-zero series, yields no trend rather than an error.
    expect($aggregates->seriesTrend([['date' => 'a', 'value' => 5]]))->toBe(0.0);
    expect($aggregates->seriesTrend([['date' => 'a', 'value' => 0]]))->toBe(0.0);
});

it('re-keys a chart series into a sparkline series', function (): void {
    expect(app(StatAggregates::class)->toStatSeries([
        ['date' => '2026-01-01', 'enrollments' => 7],
    ], 'enrollments'))->toBe([['date' => '2026-01-01', 'value' => 7]]);
});

it('builds a monthly series with an ISO month-start date', function (): void {
    $series = app(StatAggregates::class)->monthlySeries(Student::query());

    foreach ($series as $point) {
        expect($point['date'])->toMatch('/^\d{4}-\d{2}-01$/');
    }
});
