<?php

declare(strict_types=1);

use App\Filament\Widgets\CourseDistributionChart;
use App\Filament\Widgets\EnrollmentStatusChart;
use App\Filament\Widgets\RecentEnrollmentsTable;
use App\Filament\Widgets\StudentEnrollmentStatsOverview;
use App\Filament\Widgets\StudentEnrollmentTrendsChart;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('can instantiate StudentEnrollmentStatsOverview widget', function () {
    $widget = new StudentEnrollmentStatsOverview;

    expect($widget)->toBeInstanceOf(StudentEnrollmentStatsOverview::class);
});

it('can instantiate StudentEnrollmentTrendsChart widget', function () {
    $widget = new StudentEnrollmentTrendsChart;

    expect($widget)->toBeInstanceOf(StudentEnrollmentTrendsChart::class);
});

it('can instantiate CourseDistributionChart widget', function () {
    $widget = new CourseDistributionChart;

    expect($widget)->toBeInstanceOf(CourseDistributionChart::class);
});

it('can instantiate EnrollmentStatusChart widget', function () {
    $widget = new EnrollmentStatusChart;

    expect($widget)->toBeInstanceOf(EnrollmentStatusChart::class);
});

it('can instantiate RecentEnrollmentsTable widget', function () {
    $widget = new RecentEnrollmentsTable;

    expect($widget)->toBeInstanceOf(RecentEnrollmentsTable::class);
});

it('widgets have correct class names', function () {
    expect(StudentEnrollmentStatsOverview::class)->toBe('App\Filament\Widgets\StudentEnrollmentStatsOverview');
    expect(StudentEnrollmentTrendsChart::class)->toBe('App\Filament\Widgets\StudentEnrollmentTrendsChart');
    expect(CourseDistributionChart::class)->toBe('App\Filament\Widgets\CourseDistributionChart');
    expect(EnrollmentStatusChart::class)->toBe('App\Filament\Widgets\EnrollmentStatusChart');
    expect(RecentEnrollmentsTable::class)->toBe('App\Filament\Widgets\RecentEnrollmentsTable');
});

it('widgets have correct static properties', function () {
    expect(class_exists(StudentEnrollmentStatsOverview::class))->toBeTrue();
    expect(class_exists(StudentEnrollmentTrendsChart::class))->toBeTrue();
    expect(class_exists(CourseDistributionChart::class))->toBeTrue();
    expect(class_exists(EnrollmentStatusChart::class))->toBeTrue();
    expect(class_exists(RecentEnrollmentsTable::class))->toBeTrue();
});

it('widgets extend proper base classes', function () {
    expect(is_subclass_of(StudentEnrollmentStatsOverview::class, \Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
    expect(is_subclass_of(StudentEnrollmentTrendsChart::class, \Filament\Widgets\ChartWidget::class))->toBeTrue();
    expect(is_subclass_of(CourseDistributionChart::class, \Filament\Widgets\ChartWidget::class))->toBeTrue();
    expect(is_subclass_of(EnrollmentStatusChart::class, \Filament\Widgets\ChartWidget::class))->toBeTrue();
    expect(is_subclass_of(RecentEnrollmentsTable::class, \Filament\Widgets\TableWidget::class))->toBeTrue();
});

it('widgets have correct sort orders', function () {
    expect(StudentEnrollmentStatsOverview::getSort())->toBe(1);
    expect(StudentEnrollmentTrendsChart::getSort())->toBe(6);
    expect(CourseDistributionChart::getSort())->toBe(3);
    expect(RecentEnrollmentsTable::getSort())->toBe(4);
    expect(EnrollmentStatusChart::getSort())->toBe(5);
});
