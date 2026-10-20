<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Filament\Widgets\StudentEnrollmentByTypeChart;
use App\Models\Student;
use App\Models\StudentEnrollment;

/**
 * The enrollment chart used to run one COUNT query per month and per student
 * type, which meant more than fifty statements for a yearly filter. The
 * grouped statement has to land the enrollments in the very same month
 * buckets.
 */
it('counts the enrollments per month and student type with a single statement', function (): void {
    $college = Student::factory()->create(['student_type' => StudentType::College->value]);
    $shs = Student::factory()->create(['student_type' => StudentType::SeniorHighSchool->value]);

    $collegeEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $college->id,
        'created_at' => now()->subMonths(2)->startOfMonth()->addHours(2),
    ]);

    $shsEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $shs->id,
        'created_at' => now()->subMonth()->startOfMonth()->addHours(2),
    ]);

    $queries = 0;

    Illuminate\Support\Facades\DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $chart = new StudentEnrollmentByTypeChart;
    $chart->filter = 'last_3_months';

    $data = (new ReflectionMethod($chart, 'getData'));
    $data->setAccessible(true);

    $payload = $data->invoke($chart);

    $labels = $payload['labels'];
    $collegeData = $payload['datasets'][0]['data'];
    $shsData = $payload['datasets'][1]['data'];

    expect($labels)->toHaveCount(4)
        ->and(array_sum($collegeData))->toBe(1)
        ->and(array_sum($shsData))->toBe(1)
        ->and($queries)->toBe(1);

    expect($collegeEnrollment->fresh()->id)->toBe($collegeEnrollment->id)
        ->and($shsEnrollment->fresh()->id)->toBe($shsEnrollment->id);
});
