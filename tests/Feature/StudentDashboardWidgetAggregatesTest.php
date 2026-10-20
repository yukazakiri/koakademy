<?php

declare(strict_types=1);

use App\Enums\AttritionCategory;
use App\Enums\EmploymentStatus;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Filament\Widgets\AttritionByReasonChart;
use App\Filament\Widgets\EmploymentStatusChart;
use App\Filament\Widgets\StudentAcademicYearChart;
use App\Filament\Widgets\StudentGenderDistributionChart;
use App\Filament\Widgets\StudentStatsOverview;
use App\Filament\Widgets\StudentStatusChart;
use App\Filament\Widgets\StudentTypeDistributionChart;
use App\Filament\Widgets\StudentYearLevelChart;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard widgets used to run one COUNT query per enum value, which
 * turned a single page into dozens of full scans over the `students` table.
 * The grouped queries below have to return exactly the same numbers.
 */
beforeEach(function (): void {
    Student::query()->delete();

    Student::factory()->create([
        'status' => StudentStatus::Enrolled->value,
        'student_type' => StudentType::College->value,
        'gender' => 'male',
        'academic_year' => 1,
    ]);

    Student::factory()->create([
        'status' => StudentStatus::Enrolled->value,
        'student_type' => StudentType::SeniorHighSchool->value,
        'gender' => 'female',
        'academic_year' => 2,
    ]);

    Student::factory()->create([
        'status' => StudentStatus::Graduated->value,
        'student_type' => StudentType::College->value,
        'gender' => 'male',
        'academic_year' => 5,
        'employment_status' => EmploymentStatus::Employed->value,
    ]);

    Student::factory()->create([
        'status' => StudentStatus::Graduated->value,
        'student_type' => StudentType::College->value,
        'gender' => 'female',
        'academic_year' => 5,
        'employment_status' => EmploymentStatus::Unemployed->value,
    ]);

    Student::factory()->create([
        'status' => StudentStatus::Withdrawn->value,
        'student_type' => StudentType::College->value,
        'gender' => 'other',
        'academic_year' => 3,
        'attrition_category' => AttritionCategory::Academic->value,
    ]);
});

/**
 * @return array<string, mixed>
 */
function widgetData(object $widget, string $method = 'getData'): array
{
    $reflection = new ReflectionMethod($widget, $method);
    $reflection->setAccessible(true);

    $data = $reflection->invoke($widget);

    return is_array($data) ? $data : [];
}

it('counts the students by status with a single grouped query', function (): void {
    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $data = widgetData(new StudentStatusChart);

    expect($data['labels'])->toBe(['Enrolled', 'Applicant', 'Graduated', 'Withdrawn', 'Dropped'])
        ->and($data['datasets'][0]['data'])->toBe([2, 0, 2, 1, 0])
        ->and($queries)->toBe(1);
});

it('counts the students by type with a single grouped query', function (): void {
    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $data = widgetData(new StudentTypeDistributionChart);

    expect($data['labels'])->toBe(['College', 'Senior High School', 'TESDA', 'DHRT'])
        ->and($data['datasets'][0]['data'])->toBe([4, 1, 0, 0])
        ->and($queries)->toBe(1);
});

it('counts the students by year level with a single grouped query', function (): void {
    $data = widgetData(new StudentYearLevelChart);

    expect($data['datasets'][0]['data'])->toBe([1, 1, 1, 0, 2]);
});

it('counts the college students by year level with a single grouped query', function (): void {
    $data = widgetData(new StudentAcademicYearChart);

    expect($data['datasets'][0]['data'])->toBe([1, 0, 1, 0, 2]);
});

it('counts the students by gender with a single grouped query', function (): void {
    $data = widgetData(new StudentGenderDistributionChart);

    expect($data['labels'])->toBe(['Male', 'Female', 'Other'])
        ->and($data['datasets'][0]['data'])->toBe([2, 2, 1]);
});

it('counts the graduate employment statuses with a single grouped query', function (): void {
    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $data = widgetData(new EmploymentStatusChart);

    expect($data['datasets'][0]['data'])->toBe([1, 1])
        ->and($queries)->toBe(1);
});

it('counts the attrition categories with a single grouped query', function (): void {
    $data = widgetData(new AttritionByReasonChart);

    expect($data['datasets'][0]['data'])->toBe([1]);
});

it('resolves every student counter with a single statement', function (): void {
    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $stats = widgetData(new StudentStatsOverview, 'getStats');

    expect($stats)->toHaveCount(4)
        ->and($stats[0]->getLabel())->toBe('Total Students')
        ->and($stats[0]->getValue())->toBe('5')
        ->and($stats[1]->getValue())->toBe('2')
        ->and($stats[2]->getValue())->toBe('2')
        ->and($queries)->toBe(1);
});
