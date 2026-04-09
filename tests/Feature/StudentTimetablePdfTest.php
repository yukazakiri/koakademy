<?php

declare(strict_types=1);

use App\Models\Student;
use App\Services\StudentTimetablePdfService;

it('can generate a timetable filename for a student', function () {
    $student = Student::factory()->create([
        'student_id' => 123456,
    ]);

    $service = new StudentTimetablePdfService();
    $filename = $service->generateFilename($student);

    expect($filename)->toContain('timetable_123456_')
        ->and($filename)->toContain('.pdf');
});

it('returns timetable data structure for student with no classes', function () {
    $student = Student::factory()->create();

    $service = new StudentTimetablePdfService();

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getTimetableData');
    $method->setAccessible(true);

    $data = $method->invoke($service, $student);

    expect($data)->toBeArray()
        ->toHaveKey('grid')
        ->toHaveKey('days')
        ->toHaveKey('time_slots');
});
