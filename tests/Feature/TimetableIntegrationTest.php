<?php

declare(strict_types=1);

use App\Models\Student;
use App\Services\StudentTimetablePdfService;

it('can create timetable PDF service and generate filename', function () {
    // Create a simple student instance without database dependencies
    $student = new Student();
    $student->student_id = 'TEST001';

    $service = new StudentTimetablePdfService();
    $filename = $service->generateFilename($student);

    expect($filename)->toBeString();
    expect($filename)->toContain('timetable_');
    expect($filename)->toContain('.pdf');
});

it('can get timetable data structure', function () {
    // Test the timetable data structure without database
    $student = new Student();
    $student->student_id = 'TEST001';

    $service = new StudentTimetablePdfService();

    // Use reflection to access private method for testing
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getTimetableData');
    $method->setAccessible(true);

    $timetableData = $method->invoke($service, $student);

    // Verify the structure
    expect($timetableData)->toBeArray();
    expect($timetableData)->toHaveKey('time_slots');
    expect($timetableData)->toHaveKey('days');
    expect($timetableData)->toHaveKey('grid');

    // Verify time slots
    expect($timetableData['time_slots'])->toBeArray();

    // Verify days
    expect($timetableData['days'])->toBeArray();
    expect($timetableData['days'])->toHaveCount(6); // Monday to Saturday
    expect($timetableData['days'][0])->toBe('Monday');
});

it('can generate proper time slots for timetable', function () {
    $student = new Student();
    $student->student_id = 'TEST001';

    $service = new StudentTimetablePdfService();

    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('generateTimeSlots');
    $method->setAccessible(true);

    $timeSlots = $method->invoke($service, collect());

    expect($timeSlots)->toBeArray();
    expect($timeSlots)->toBeEmpty();
});

it('can map days correctly', function () {
    $student = new Student();
    $student->student_id = 'TEST001';

    $service = new StudentTimetablePdfService();

    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getDays');
    $method->setAccessible(true);

    $days = $method->invoke($service);

    expect($days)->toBeArray();
    expect($days)->toHaveCount(6);
    expect($days)->toEqual(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);
});

it('can calculate slot duration correctly', function () {
    $student = new Student();
    $student->student_id = 'TEST001';

    $service = new StudentTimetablePdfService();

    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateSlotDuration');
    $method->setAccessible(true);

    // Test 1-hour class
    $duration = $method->invoke($service, '09:00:00', '10:00:00');
    expect($duration)->toBe(2);

    // Test 2-hour class
    $duration = $method->invoke($service, '09:00:00', '11:00:00');
    expect($duration)->toBe(4);

    // Test 3-hour class
    $duration = $method->invoke($service, '09:00:00', '12:00:00');
    expect($duration)->toBe(6);
});
